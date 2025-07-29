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

    /**
     * Constructor for Exchange365Transport
     *
     * @param array $mailSettings Mail configuration settings
     * @param EventDispatcherInterface|null $dispatcher Event dispatcher instance (optional)
     * @param LoggerInterface|null $logger Logger instance (optional)
     */
    public function  __construct(array $mailSettings, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        $eventDispatcherAdapter = GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Adapter\EventDispatcherAdapter::class
        );

        parent::__construct($dispatcher ?? $eventDispatcherAdapter);

        // Initialize the logger using TYPO3's logging system
        $this->logger = $logger ?? GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->mailSettings = $mailSettings;
    }

    /**
     * Sends the email using Microsoft Graph API.
     *
     * @param SentMessage $message The email message to be sent.
     * @throws RuntimeException If sending fails.
     */
    protected function doSend(SentMessage $message): void
    {
        $confFromEmail = '';

        try {
            // Get configuration from different sources
            $conf = $this->getConfiguration();

            // Validate required configuration
            $this->validateConfiguration($conf);

            // Setup authentication context
            $tokenRequestContext = new ClientCredentialContext(
                $conf['tenantId'],
                $conf['clientId'],
                $conf['clientSecret']
            );

            $graphServiceClient = new GraphServiceClient($tokenRequestContext);

            // Convert to Microsoft Graph message format
            $graphMessage = MSGraphMailApiService::convertToGraphMessage($message);

            // Determine from email address
            $confFromEmail = $graphMessage['from']
                ?? $conf['fromEmail']
                ?? $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']
                ?? '';

            if (empty($confFromEmail)) {
                throw new RuntimeException('No valid "from" email address found in configuration.');
            }

            // Prepare request body
            $requestBody = new SendMailPostRequestBody();
            $requestBody->setMessage($graphMessage['message']);
            // Ensure boolean conversion for saveToSentItems
            $requestBody->setSaveToSentItems((bool)($conf['saveToSentItems'] ?? false));

            // Send the email using Microsoft Graph API
            $graphServiceClient->users()->byUserId($confFromEmail)->sendMail()->post($requestBody)->wait();

            $this->logger->debug('Mail sent successfully with ' . self::class . ' from ' . $confFromEmail);
        } catch (Exception $e) {
            $errorMessage = 'Sending mail' . ($confFromEmail ? " from {$confFromEmail}" : '') . ' failed: ' . $e->getMessage();
            $this->logger->alert($errorMessage);
            throw new RuntimeException("Sending mail with Exchange365 mailer failed. Please check credentials setup. Error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get configuration from TypoScript or mail settings
     * 
     * @return array
     * @throws RuntimeException
     */
    private function getConfiguration(): array
    {
        // Try to get configuration from TypoScript first
        $conf = $this->getTypoScriptConfiguration();

        // Fallback to mail settings if TypoScript not available
        if (empty($conf)) {
            $conf = $this->getMailSettingsConfiguration();
        }

        if (empty($conf)) {
            throw new RuntimeException('Exchange 365 mail configuration not found.');
        }

        return $conf;
    }

    /**
     * Get configuration from TypoScript (TYPO3 12 compatible)
     * 
     * @return array|null
     */
    private function getTypoScriptConfiguration(): ?array
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        // Check if frontend mode (applicationType 1 = frontend)
        if ($request?->getAttribute('applicationType') !== 1) {
            return null;
        }

        $currentVersion = VersionNumberUtility::getNumericTypo3Version();

        // TYPO3 12.4.1+ uses the new TypoScript API
        if (version_compare($currentVersion, "12.4.1", ">=")) {
            $frontendTypoScript = $request->getAttribute('frontend.typoscript');
            if ($frontendTypoScript === null) {
                return null;
            }

            $fullTypoScript = $frontendTypoScript->getSetupArray();
            return $fullTypoScript['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'] ?? null;
        } else {
            // Fallback for older TYPO3 versions (should be removed when TYPO3 11 support is dropped)
            return $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'] ?? null;
        }
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
     * Returns the name of the transport.
     *
     * @return string The transport name.
     */
    public function __toString(): string
    {
        return 'exchange365api';
    }
}
