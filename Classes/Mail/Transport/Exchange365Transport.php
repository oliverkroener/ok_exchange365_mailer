<?php

namespace OliverKroener\OkExchange365\Mail\Transport;

use Exception;
use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Graph\GraphServiceClient;
use OliverKroener\Helpers\MSGraphApi\MSGraphMailApiService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;

class Exchange365Transport extends AbstractTransport
{
    private array $mailSettings;
    private LoggerInterface $logger;

    public function  __construct(array $mailSettings, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        // get the dispatcher (normally in Classes/Mail/TransportFactory.php but not for custom transports)
        // we need this to dispatch some events like onMEssage send etc.
        if (class_exists('TYPO3\CMS\Core\Adapter\EventDispatcherAdapter')) {
            $eventDispatcherAdapter = GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Adapter\EventDispatcherAdapter::class
            );
        } else {
            // TODO remove if support for TYPO3 11 dropped
            $eventDispatcherAdapter = GeneralUtility::makeInstance(
                \TYPO3\SymfonyPsrEventDispatcherAdapter\EventDispatcherAdapter::class
            );
        }
        parent::__construct($dispatcher ?? $eventDispatcherAdapter);

        // Initialize the logger using TYPO3's logging system
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->mailSettings = $mailSettings;
        $this->mailSettings = $mailSettings;
    }

    /**
     * Sends the email using Microsoft Graph API.
     *
     * @param SentMessage $message The email message to be sent.
     * @param SentMessage $message The email message to be sent.
     * @throws RuntimeException If sending fails.
     */
    protected function doSend(SentMessage $message): void
    {
        try {
            // Attempt to get configuration from TypoScript if in frontend context
            $conf = null;

            $request = $GLOBALS['TYPO3_REQUEST'];

            // Check if frontend mode
            if ($request?->getAttribute('applicationType') === 1) {

                $currentVersion = VersionNumberUtility::getNumericTypo3Version();

                if (version_compare($currentVersion, "12.4.1", ">=")) {

                    $fullTypoScript = $request->getAttribute('frontend.typoscript')->getSetupArray();
                    // TypoScript configuration retrieval is not required here
                    $conf = $fullTypoScript['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'] ?? null;

                } else {

                    $conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'] ?? null;

                }
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
                throw new \RuntimeException('Exchange 365 mail configuration not found.');
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
            $this->logger->alert('Sending mail from ' . $confFromEmail . ' failed!' . PHP_EOL . $e->getMessage());
            throw new RuntimeException("Sending mail with Exchange365 mailer failed. Please check credentials setup." . $e->getTraceAsString());
        }

        $this->logger->debug('Mail sent successfully with ' . self::class);
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
