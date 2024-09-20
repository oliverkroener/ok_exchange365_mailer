<?php

namespace OliverKroener\OkExchange365\Mail\Transport;

use Exception;
use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Graph\GraphServiceClient;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use OliverKroener\Helpers\MSGraphApi\MSGraphMailApiService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;

class Exchange365Transport implements TransportInterface
{
    private array $mailSettings;
    private LoggerInterface $logger;

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
     * @param Envelope|null $envelope The envelope configuration, if any.
     * @throws RuntimeException If sending fails.
     */
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
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

            $saveToSentItems = $conf['saveToSentItems'] ?? 0;

            $tokenRequestContext = new ClientCredentialContext(
                $conf['tenantId'],
                $conf['clientId'],
                $conf['clientSecret']
            );

            $graphServiceClient = new GraphServiceClient($tokenRequestContext);

            // Convert to Microsoft Graph message format
            $graphMessage = MSGraphMailApiService::convertToGraphMessage($message);

            $confFromEmail = $graphMessage['from'];
            
            $requestBody = new SendMailPostRequestBody();
            $requestBody->setMessage($graphMessage['message']);
            $requestBody->setSaveToSentItems($saveToSentItems);

            // Send the email using Microsoft Graph API
            $graphServiceClient->users()->byUserId($confFromEmail)->sendMail()->post($requestBody)->wait();

        } catch (Exception $e) {
            $this->logger->alert('Sending mail from ' . $confFromEmail . ' failed!');
            throw new RuntimeException("Sending mail with Exchange365 mailer failed. Please check credentials setup." . $e->getTraceAsString());
        }

        $this->logger->debug('Mail sent successfully with ' . self::class);

        if (!$envelope) {
            $sentMessage = null;
        } else {
            $sentMessage = new SentMessage($message, $envelope);
        }

        return $sentMessage;
    }

    public function __toString(): string
    {
        return 'exchange365api';
    }
}
