<?php

namespace OliverKroener\OkExchange365\Mail\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Message;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Exchange365Transport extends AbstractTransport
{
    /**
     * Status codes Microsoft Graph uses for throttling and transient outages.
     */
    private const RETRY_STATUS_CODES = [429, 503, 504];
    private const MAX_RETRY_WAIT_SECONDS = 5;

    private const GRAPH_BASE_URL = 'https://graph.microsoft.com/v1.0';

    private $sentMessage;
    private $mailSettings;
    private $logger;

    /**
     * HTTP client for the token and sendMail requests, reused across mails.
     *
     * @var ClientInterface|null
     */
    private $httpClient;

    /**
     * Access token cached per credential set until shortly before it expires, so
     * a request that sends several mails authenticates only once.
     *
     * @var array{key: string, token: string, expires: int}|null
     */
    private $accessToken;

    public function __construct(array $mailSettings)
    {
        parent::__construct();
        $this->mailSettings = $mailSettings;
        // Initialize the logger using TYPO3's logging system
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Sends the email using Microsoft Graph API.
     *
     * @param SentMessage $message The email message to be sent.
     * @throws TransportException If sending fails.
     */
    public function doSend(SentMessage $message): void
    {
        $graphSenderUserId = '';

        try {
            // Get configuration from different sources
            $conf = $this->getConfiguration();

            // Validate required configuration
            $this->validateConfiguration($conf);

            $accessToken = $this->getAccessToken($conf);

            $graphSenderUserId = $this->resolveGraphSenderUserId($conf, $this->getMessageFromAddress($message));

            $rawMessage = $message->getMessage()->toString();

            // Send the email using Microsoft Graph API. The sendMail endpoint
            // targets the resolved Graph mailbox; the visible From header stays
            // in the raw MIME message untouched.
            //
            // A single POST, sent with the transport's own client so the connect
            // timeout and the retry apply (Graph SDK v1 sets no connect timeout,
            // so an unreachable IPv6 route would hang for 100 s).
            $url = self::GRAPH_BASE_URL . '/users/' . rawurlencode($graphSenderUserId) . '/sendMail';
            $this->withRetry(function () use ($url, $accessToken, $rawMessage) {
                return $this->getHttpClient()->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'text/plain',
                    ],
                    'body' => base64_encode($rawMessage),
                ]);
            });
        } catch (\Throwable $e) {
            $this->logger->error('Sending mail' . ($graphSenderUserId ? ' via Graph sender ' . $graphSenderUserId : '') . ' failed: ' . $e->getMessage(), ['exception' => $e]);
            // TransportException extends \RuntimeException, so existing catch blocks keep working.
            throw new TransportException('Sending mail with Exchange365 mailer failed. Please check credentials setup. Error: ' . $e->getMessage(), 0, $e);
        }

        $this->sentMessage = $message;
        $this->logger->debug('Mail sent successfully with ' . self::class . ' via Graph sender ' . $graphSenderUserId);
    }

    /**
     * Get an app-only access token from the Microsoft identity platform (v2.0
     * endpoint), reusing a cached one while it is valid for these credentials.
     *
     * @param array $conf
     */
    private function getAccessToken(array $conf): string
    {
        $key = hash('sha256', implode("\0", [(string)$conf['tenantId'], (string)$conf['clientId'], (string)$conf['clientSecret']]));

        if ($this->accessToken !== null && $this->accessToken['key'] === $key && $this->accessToken['expires'] > time()) {
            return $this->accessToken['token'];
        }

        $url = 'https://login.microsoftonline.com/' . rawurlencode((string)$conf['tenantId']) . '/oauth2/v2.0/token';
        $response = $this->withRetry(function () use ($url, $conf) {
            return $this->getHttpClient()->request('POST', $url, [
                'form_params' => [
                    'client_id' => (string)$conf['clientId'],
                    'client_secret' => (string)$conf['clientSecret'],
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            ]);
        });

        $token = json_decode((string)$response->getBody(), true);
        if (!is_array($token) || empty($token['access_token'])) {
            throw new RuntimeException('The Microsoft identity platform returned no access token.');
        }

        // Renew a minute early so a token never expires mid-request.
        $this->accessToken = [
            'key' => $key,
            'token' => (string)$token['access_token'],
            'expires' => time() + max(0, (int)($token['expires_in'] ?? 0) - 60),
        ];

        return $this->accessToken['token'];
    }

    /**
     * Run a request, retrying once when no connection could be made or Graph throttles
     * (429) or is briefly unavailable (503/504). Honours Retry-After, capped at a
     * few seconds so a page request is never held for long.
     *
     * @return mixed
     */
    private function withRetry(callable $request)
    {
        try {
            return $request();
        } catch (ConnectException $e) {
            // Retry only when no connection was ever established: then the request
            // provably never reached Graph. A request that stalled after connecting
            // is not repeated - Graph may already have accepted the message.
            if ((float)($e->getHandlerContext()['connect_time'] ?? 0) > 0) {
                throw $e;
            }

            return $request();
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response === null || !in_array($response->getStatusCode(), self::RETRY_STATUS_CODES, true)) {
                throw $e;
            }
            $wait = (int)$response->getHeaderLine('Retry-After');
            sleep(min(max($wait, 1), self::MAX_RETRY_WAIT_SECONDS));

            return $request();
        }
    }

    /**
     * Build the HTTP client. Protected so tests can substitute the network layer.
     */
    protected function createHttpClient(): ClientInterface
    {
        return new Client(['timeout' => 30, 'connect_timeout' => 10]);
    }

    private function getHttpClient(): ClientInterface
    {
        if ($this->httpClient === null) {
            $this->httpClient = $this->createHttpClient();
        }

        return $this->httpClient;
    }

    /**
     * The address in the message's From header, as the 3.x and 4.x lines use.
     * Falls back to the envelope sender, which differs only when a Sender header
     * is set.
     */
    private function getMessageFromAddress(SentMessage $message): string
    {
        $original = $message->getOriginalMessage();
        $header = $original instanceof Message ? $original->getHeaders()->get('From') : null;
        if ($header instanceof MailboxListHeader) {
            $addresses = $header->getAddresses();
            if (isset($addresses[0]) && $addresses[0]->getAddress() !== '') {
                return $addresses[0]->getAddress();
            }
        }

        return $message->getEnvelope()->getSender()->getAddress();
    }

    /**
     * Resolve the Microsoft Graph sender mailbox/user ID for the
     * /users/{id}/sendMail endpoint.
     *
     * This is intentionally separate from the message From address so Send As /
     * Send On Behalf scenarios can target a different mailbox than the visible
     * sender. Order: graphSenderUserId, the message From header, fromEmail,
     * MAIL.defaultMailFromAddress. Empty strings count as "unset" at every step.
     *
     * @param array $conf
     * @param mixed $messageFrom
     * @throws RuntimeException when nothing is configured
     */
    private function resolveGraphSenderUserId(array $conf, $messageFrom): string
    {
        $candidates = [
            $conf['graphSenderUserId'] ?? '',
            $messageFrom,
            $conf['fromEmail'] ?? '',
            $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && (string)$candidate !== '') {
                return (string)$candidate;
            }
        }

        throw new RuntimeException('No Microsoft Graph sender user ID could be resolved. Configure graphSenderUserId, fromEmail, or TYPO3 MAIL.defaultMailFromAddress.');
    }

    /**
     * Get configuration by merging TypoScript on top of the mail settings.
     *
     * The mail settings are the baseline, so backend, CLI and scheduler contexts
     * always have a configuration. Frontend TypoScript overlays individual values
     * on top of it.
     *
     * @return array
     */
    private function getConfiguration(): array
    {
        $conf = $this->getMailSettingsConfiguration();

        foreach ($this->getTypoScriptConfiguration() ?? [] as $key => $value) {
            // An empty TypoScript value means "not configured here" and must not
            // shadow the mail settings. saveToSentItems is exempt: a value of
            // false is flattened into an empty constant and does mean false.
            if ($key === 'saveToSentItems' || ($value !== '' && $value !== null)) {
                $conf[$key] = $value;
            }
        }

        return $conf;
    }

    /**
     * Get configuration from the frontend TypoScript setup.
     *
     * TYPO3 10 exposes the frontend TypoScript through $GLOBALS['TSFE']->tmpl->setup.
     * Returns null outside the frontend, where TSFE is absent.
     *
     * @return array|null
     */
    private function getTypoScriptConfiguration(): ?array
    {
        $setup = isset($GLOBALS['TSFE']->tmpl->setup) ? $GLOBALS['TSFE']->tmpl->setup : null;

        if (!is_array($setup)) {
            return null;
        }

        return $setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'] ?? null;
    }

    /**
     * Get configuration from mail settings
     *
     * @return array
     */
    private function getMailSettingsConfiguration(): array
    {
        return [
            'tenantId' => $this->mailSettings['transport_exchange365_tenantId'] ?? '',
            'clientId' => $this->mailSettings['transport_exchange365_clientId'] ?? '',
            'clientSecret' => $this->mailSettings['transport_exchange365_clientSecret'] ?? '',
            'fromEmail' => $this->mailSettings['transport_exchange365_fromEmail'] ?? '',
            'graphSenderUserId' => $this->mailSettings['transport_exchange365_graphSenderUserId'] ?? '',
            'saveToSentItems' => $this->mailSettings['transport_exchange365_saveToSentItems'] ?? '0',
        ];
    }

    /**
     * Validate required configuration values
     *
     * @param array $conf
     * @throws RuntimeException
     */
    private function validateConfiguration(array $conf): void
    {
        $requiredFields = ['tenantId', 'clientId', 'clientSecret'];

        foreach ($requiredFields as $field) {
            if (empty($conf[$field])) {
                throw new RuntimeException("Exchange 365 configuration missing required field: {$field}");
            }
        }
    }

    /**
     * Gets the last sent message.
     *
     * @return SentMessage|null The sent message or null if none was sent.
     */
    public function getSentMessage(): ?SentMessage
    {
        return $this->sentMessage;
    }

    public function __toString(): string
    {
        return 'exchange365mailer';
    }
}
