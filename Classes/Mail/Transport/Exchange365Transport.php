<?php

namespace OliverKroener\OkExchange365\Mail\Transport;

use Exception;
use Microsoft\Graph\Graph;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use OliverKroener\Helpers\MSGraphApi\MSGraphMailApiService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class Exchange365Transport extends AbstractTransport
{
    private $sentMessage;
    private $mailSettings;
    private $logger;

    public function  __construct(array $mailSettings)
    {
        $this->mailSettings = $mailSettings;
        // Initialize the logger using TYPO3's logging system
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Sends the email using Microsoft Graph API.
     *
     * @param RawMessage $message The email message to be sent.
     * @param Envelope $envelope The envelope containing sender and recipients.
     * @return SentMessage The sent message.
     * @throws RuntimeException If sending fails.
     */
    public function doSend(SentMessage $message): void
    {
        try {
            // Attempt to get configuration from TypoScript if in frontend context
            $conf = null;

            // Check if frontend mode
            $conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'] ?? null;

            // If configuration not found, try to get from mail settings
            if (empty($conf)) {
                $conf = [];
                $conf['tenantId'] = $this->mailSettings['transport_exchange365_tenantId'] ?? '';
                $conf['clientId'] = $this->mailSettings['transport_exchange365_clientId'] ?? '';
                $conf['clientSecret'] = $this->mailSettings['transport_exchange365_clientSecret'] ?? '';
                $conf['fromEmail'] = $this->mailSettings['transport_exchange365_fromEmail'] ?? '';
                $conf['saveToSentItems'] = $this->mailSettings['transport_exchange365_saveToSentItems'] ?? '';
            }

            if (empty($conf['tenantId']) || empty($conf['clientId']) || empty($conf['clientSecret'])) {
                throw new \RuntimeException('Exchange 365 mail configuration not found or incomplete. Please check tenantId, clientId, and clientSecret.');
            }

            $guzzle = new \GuzzleHttp\Client();
            $url = 'https://login.microsoftonline.com/' . $conf['tenantId'] . '/oauth2/token?api-version=1.0';
            $token = json_decode($guzzle->post($url, [
                'form_params' => [
                    'client_id' => $conf['clientId'],
                    'client_secret' => $conf['clientSecret'],
                    'resource' => 'https://graph.microsoft.com/',
                    'grant_type' => 'client_credentials',
                ],
            ])->getBody()->getContents());

            $accessToken = $token->access_token;

            $graph = new Graph();
            $graph->setAccessToken($accessToken);

            // Get FROM email address
            $confFromEmail = $message->getEnvelope()->getSender()->getAddress() ?? $conf['fromEmail'] ?? '';

            $rawMessage = $message->getMessage()->toString();

            // Send the email using Microsoft Graph API
            $urlSuffix = '/users/' . urlencode($confFromEmail) . '/sendMail';
            $graph->createRequest('POST', $urlSuffix)
                        ->addHeaders(['Content-Type' => 'text/plain'])
                        ->attachBody(base64_encode($rawMessage)) // $sendMailPostRequestBody)
                        ->execute();

        } catch (Exception $e) {
            $this->logger->alert('Sending mail from ' . $confFromEmail . ' failed!');
            throw new RuntimeException("Sending mail with Exchange365 mailer failed. Please check credentials setup." . $e->getMessage());
        }

        $this->logger->debug('Mail sent successfully with ' . self::class);
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
