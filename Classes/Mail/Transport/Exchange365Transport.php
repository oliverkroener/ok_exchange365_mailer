<?php

namespace OliverKroener\OkExchange365\Mail\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAccessTokenProvider;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAuthenticationProvider;
use Microsoft\Graph\Core\GraphClientFactory;
use Microsoft\Graph\Core\NationalCloud;
use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody;
use Microsoft\Graph\GraphRequestAdapter;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Kiota\Authentication\Oauth\ProviderFactory;
use OliverKroener\Helpers\MSGraphApi\MSGraphMailApiService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Exchange365Transport extends AbstractTransport
{
    private array $mailSettings;
    private LoggerInterface $logger;

    /**
     * Graph client reused for every message this transport sends with the same
     * credentials. The client keeps its OAuth token in memory, so a request that
     * sends several mails (a form, a scheduler run) authenticates only once.
     * The Graph SDK's own middleware already retries 429/503/504 with Retry-After.
     */
    private ?GraphServiceClient $graphServiceClient = null;
    private string $graphServiceClientKey = '';

    public function __construct(array $mailSettings, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        // Symfony Mailer 5 expects a Symfony dispatcher, so a PSR-14 dispatcher
        // passed in is wrapped in TYPO3's adapter rather than ignored.
        parent::__construct(
            $dispatcher !== null
                ? new \TYPO3\SymfonyPsrEventDispatcherAdapter\EventDispatcherAdapter($dispatcher)
                : GeneralUtility::makeInstance(\TYPO3\SymfonyPsrEventDispatcherAdapter\EventDispatcherAdapter::class)
        );

        // Initialize the logger using TYPO3's logging system
        $this->logger = $logger ?? GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->mailSettings = $mailSettings;
    }

    /**
     * Sends the email using Microsoft Graph API.
     *
     * @param SentMessage $message The email message to be sent.
     * @throws TransportException If sending fails.
     */
    protected function doSend(SentMessage $message): void
    {
        $graphSenderUserId = '';

        try {
            // Get configuration from different sources
            $conf = $this->getConfiguration();

            // Validate required configuration
            $this->validateConfiguration($conf);

            $saveToSentItems = (bool)($conf['saveToSentItems'] ?? 0);

            $graphServiceClient = $this->getGraphServiceClient($conf);

            // Convert to Microsoft Graph message format
            $graphMessage = MSGraphMailApiService::convertToGraphMessage($message);

            $graphSenderUserId = $this->resolveGraphSenderUserId($conf, $graphMessage['from'] ?? null);

            $requestBody = new SendMailPostRequestBody();
            $requestBody->setMessage($graphMessage['message']);
            $requestBody->setSaveToSentItems($saveToSentItems);

            // Send the email using Microsoft Graph API
            $graphServiceClient->users()->byUserId($graphSenderUserId)->sendMail()->post($requestBody)->wait();
        } catch (\Throwable $e) {
            $this->logger->error('Sending mail' . ($graphSenderUserId ? " via Graph sender {$graphSenderUserId}" : '') . ' failed: ' . $e->getMessage(), ['exception' => $e]);
            // TransportException extends \RuntimeException, so existing catch blocks keep working.
            throw new TransportException('Sending mail with Exchange365 mailer failed. Please check credentials setup. Error: ' . $e->getMessage(), 0, $e);
        }

        $this->logger->debug('Mail sent successfully with ' . self::class . ' via Graph sender ' . $graphSenderUserId);
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
     * @param array<string, mixed> $conf
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
     * Return the Graph client for these credentials, creating it only when the
     * credentials differ from the ones the cached client was built with.
     *
     * @param array<string, mixed> $conf
     */
    private function getGraphServiceClient(array $conf): GraphServiceClient
    {
        $key = hash('sha256', implode("\0", [(string)$conf['tenantId'], (string)$conf['clientId'], (string)$conf['clientSecret']]));

        if ($this->graphServiceClient === null || $this->graphServiceClientKey !== $key) {
            $this->graphServiceClient = $this->createGraphServiceClient($conf);
            $this->graphServiceClientKey = $key;
        }

        return $this->graphServiceClient;
    }

    /**
     * Build a Graph client. Protected so tests can substitute the network layer.
     *
     * Same construction as the SDK's default, but with tighter timeouts: the SDK
     * waits up to 100 s for a response, which would hold a frontend request (a
     * form submit) for that long when a connection stalls. A stalled sendMail is
     * deliberately NOT retried - Graph may already have accepted the message.
     * Throttling (429) and 503/504 are still retried by the SDK's middleware.
     *
     * @param array<string, mixed> $conf
     */
    protected function createGraphServiceClient(array $conf): GraphServiceClient
    {
        $tokenRequestContext = new ClientCredentialContext(
            (string)$conf['tenantId'],
            (string)$conf['clientId'],
            (string)$conf['clientSecret']
        );
        $timeouts = [
            RequestOptions::CONNECT_TIMEOUT => 10,
            RequestOptions::TIMEOUT => 30,
        ];
        // The OAuth library builds its own HTTP client WITHOUT any timeout, so a
        // stalled token request would block a CLI or scheduler run forever.
        $oauthProvider = ProviderFactory::create($tokenRequestContext, ['httpClient' => new Client($timeouts)]);
        $requestAdapter = new GraphRequestAdapter(
            GraphPhpLeagueAuthenticationProvider::createWithAccessTokenProvider(
                new GraphPhpLeagueAccessTokenProvider($tokenRequestContext, [], NationalCloud::GLOBAL, null, $oauthProvider)
            ),
            GraphClientFactory::createWithConfig($timeouts)
        );
        $requestAdapter->setBaseUrl(NationalCloud::GLOBAL . '/v1.0');

        return new GraphServiceClient($tokenRequestContext, [], NationalCloud::GLOBAL, $requestAdapter);
    }

    /**
     * Get configuration by merging TypoScript on top of the mail settings.
     *
     * The mail settings are the baseline, so backend, CLI and scheduler contexts
     * always have a configuration. Frontend TypoScript overlays individual values
     * on top of it.
     *
     * @return array<string, mixed>
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
     * TYPO3 11 exposes the frontend TypoScript through $GLOBALS['TSFE']->tmpl->setup;
     * the "frontend.typoscript" request attribute used on TYPO3 13 and 14 does not
     * exist here. Returns null outside the frontend, where TSFE is absent.
     *
     * @return array<string, mixed>|null
     */
    private function getTypoScriptConfiguration(): ?array
    {
        $setup = $GLOBALS['TSFE']->tmpl->setup ?? null;

        if (!is_array($setup)) {
            return null;
        }

        return $setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'] ?? null;
    }

    /**
     * Get configuration from mail settings
     *
     * @return array<string, mixed>
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
     * @param array<string, mixed> $conf
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
     * Returns the name of the transport.
     *
     * @return string The transport name.
     */
    public function __toString(): string
    {
        return 'exchange365api';
    }
}
