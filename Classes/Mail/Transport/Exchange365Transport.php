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
    private $started = false; // Track whether the transport is started

    public function __construct(array $mailSettings)
    {
        // Initialize the logger using TYPO3's logging system
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Sends the email using Microsoft Graph API.
     *
     * @param \Swift_Mime_Message $message The message to send
     * @param string[] &$failedRecipients To collect failures by-reference, nothing will fail in our debugging case
     * @return int
     * @throws \RuntimeException
     */
    public function send(\Swift_Mime_Message $message, &$failedRecipients = null)
    {
        if (!$this->isStarted()) {
            $this->start();
        }

        try {
            $conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'];

            $confFromEmail = $conf['fromEmail'];
            $saveSentEmails = $conf['saveSentEmails'] ?? 0;

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

            // Convert to Microsoft Graph message format
            $graphMessage = MSGraphMailApiService::convertToGraphMessage($message->toString(), $confFromEmail);

            $sendMailPostRequestBody = [
                'message' => json_decode(json_encode($graphMessage), true),
                'saveToSentItems' => $saveSentEmails == 1 ? 'true' : 'false'
            ];

            // Send the email using Microsoft Graph API
            $urlSuffix = '/users/' . urlencode($confFromEmail) . '/sendMail';
            $graph->createRequest('POST', $urlSuffix)
                  ->attachBody($sendMailPostRequestBody)
                  ->execute();

            $this->logger->debug('Mail sent successfully with ' . self::class);

            return count($message->getTo()); // Return the number of recipients

        } catch (Exception $e) {
            $this->logger->alert('Sending mail from ' . $confFromEmail . ' failed: ' . $e->getMessage());
            $failedRecipients[] = $confFromEmail;
            return 0;
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
        // This method is required for Swift_Transport interface, but you may choose not to implement any specific functionality.
    }

    public function __toString(): string
    {
        return 'exchange365mailer';
    }
}
