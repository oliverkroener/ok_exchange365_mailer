<?php

namespace OliverKroener\OkExchange365\Mail\Transport;

use Exception;
use Microsoft\Graph\Graph;
use Swift_Transport;
use Swift_Events_EventListener;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use OliverKroener\Helpers\MSGraphApi\MSGraphMailApiService;

class Exchange365Transport implements Swift_Transport
{
    private $logger;
    private $mailSettings;
    private $started = false; // Track whether the transport is started

    public function __construct(array $mailSettings)
    {
        $this->mailSettings = $mailSettings;
        // Initialize the logger using TYPO3's logging system
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Sends the email using Microsoft Graph API.
     *
     * @param \Swift_Mime_Message $message The message to send
     * @param string[] &$failedRecipients To collect failures by-reference
     * @return int
     * @throws \RuntimeException
     */
    public function send(\Swift_Mime_Message $message, &$failedRecipients = null)
    {
        if (!$this->isStarted()) {
            $this->start();
        }

        try {
            // Attempt to get configuration from TypoScript if in frontend context
            if (isset($GLOBALS['TSFE'])) {
                $conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'] ?? null;
            }

            // If configuration not found, try to get from mail settings
            if (empty($conf)) {
                $conf = [];
                $conf['tenantId'] = $this->mailSettings['transport_exchange365_tenantId'] ?? '';
                $conf['clientId'] = $this->mailSettings['transport_exchange365_clientId'] ?? '';
                $conf['clientSecret'] = $this->mailSettings['transport_exchange365_clientSecret'] ?? '';
                $conf['fromEmail'] = $this->mailSettings['transport_exchange365_fromEmail'] ?? '';
                $conf['saveToSentItems'] = $this->mailSettings['transport_exchange365_saveToSentItems'] ?? '';
            }

            if (empty($conf)) {
                throw new \RuntimeException('Exchange365 mail configuration not found.');
            }

            $confFromEmail = $conf['fromEmail'] ?? '';
            $saveToSentItems = $conf['saveToSentItems'] ?? 0;

            $guzzle = new \GuzzleHttp\Client();
            $url = 'https://login.microsoftonline.com/' . $conf['tenantId'] . '/oauth2/token?api-version=1.0';
            $tokenResponse = $guzzle->post($url, [
                'form_params' => [
                    'client_id' => $conf['clientId'],
                    'client_secret' => $conf['clientSecret'],
                    'resource' => 'https://graph.microsoft.com/',
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $token = json_decode($tokenResponse->getBody()->getContents());

            if (!isset($token->access_token)) {
                throw new \RuntimeException('Failed to obtain access token.');
            }

            $accessToken = $token->access_token;

            $graph = new Graph();
            $graph->setAccessToken($accessToken);

            // Convert to Microsoft Graph message format
            $graphMessage = MSGraphMailApiService::convertToGraphMessage($message->toString(), $confFromEmail);

            $sendMailPostRequestBody = [
                'message' => json_decode(json_encode($graphMessage), true),
                'saveToSentItems' => $saveToSentItems == 1 ? 'true' : 'false',
            ];

            // Send the email using Microsoft Graph API
            $urlSuffix = '/users/' . urlencode($confFromEmail) . '/sendMail';
            $graph->createRequest('POST', $urlSuffix)
                  ->attachBody($sendMailPostRequestBody)
                  ->execute();

            $this->logger->debug('Mail sent successfully with ' . __CLASS__);

            return count((array) $message->getTo()); // Return the number of recipients

        } catch (Exception $e) {
            $this->logger->alert('Sending mail from ' . $confFromEmail . ' failed: ' . $e->getMessage());
            throw new \RuntimeException('Sending mail from ' . $confFromEmail . ' failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if the transport is started.
     *
     * @return bool True if started, false otherwise.
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Start the transport.
     */
    public function start()
    {
        $this->started = true;
    }

    /**
     * Stop the transport.
     */
    public function stop()
    {
        $this->started = false;
    }

    /**
     * Register a plugin.
     *
     * @param Swift_Events_EventListener $plugin The plugin to register.
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        // This method is required by the Swift_Transport interface but can remain empty if not needed.
    }

    public function __toString(): string
    {
        return 'exchange365mailer';
    }
}
