<?php

namespace OliverKroener\OkExchange365\Mail\Transport;

use Exception;
use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use OliverKroener\Helpers\MSGraphApi\MSGraphMailApiService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Exchange365Transport extends AbstractTransport
{
    private array $mailSettings;
    private LoggerInterface $logger;

    public function __construct(array $mailSettings, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        // TODO remove if support for TYPO3 11 dropped
        $eventDispatcherAdapter = GeneralUtility::makeInstance(
            \TYPO3\SymfonyPsrEventDispatcherAdapter\EventDispatcherAdapter::class
        );

        parent::__construct($eventDispatcherAdapter);

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
        $graphSenderUserId = '';

        try {
            // Attempt to get configuration from TypoScript if in frontend context
            $conf = null;

            $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

            // Check if frontend mode
            $conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'] ?? null;

            // If configuration not found, try to get from mail settings
            if (empty($conf)) {
                $conf = [];
                $conf['tenantId'] = $this->mailSettings['transport_exchange365_tenantId'] ?? '';
                $conf['clientId'] = $this->mailSettings['transport_exchange365_clientId'] ?? '';
                $conf['clientSecret'] = $this->mailSettings['transport_exchange365_clientSecret'] ?? '';
                $conf['fromEmail'] = $this->mailSettings['transport_exchange365_fromEmail'] ?? '';
                $conf['graphSenderUserId'] = $this->mailSettings['transport_exchange365_graphSenderUserId'] ?? '';
                $conf['saveToSentItems'] = $this->mailSettings['transport_exchange365_saveToSentItems'] ?? '';
            }

            if (empty($conf['tenantId']) || empty($conf['clientId']) || empty($conf['clientSecret'])) {
                throw new \RuntimeException('Exchange 365 mail configuration not found or incomplete. Please check tenantId, clientId, and clientSecret.');
            }

            $saveToSentItems = (bool)($conf['saveToSentItems'] ?? 0);

            $tokenRequestContext = new ClientCredentialContext(
                $conf['tenantId'],
                $conf['clientId'],
                $conf['clientSecret']
            );

            // With specific scopes
            //$scopes = ['Mail.Send', 'User.ReadBasic.All'];
            $graphServiceClient = new GraphServiceClient($tokenRequestContext);

            // Convert to Microsoft Graph message format
            $graphMessage = MSGraphMailApiService::convertToGraphMessage($message);

            // Resolve the Microsoft Graph sender mailbox/user ID for the
            // /users/{id}/sendMail endpoint. This is intentionally separate
            // from the message From address so Send As / Send On Behalf
            // scenarios can target a different mailbox than the visible sender.
            // Empty strings from the mail settings must be treated as "unset",
            // so use !empty() instead of a bare ?? chain.
            $graphSenderUserId = !empty($conf['graphSenderUserId'])
                ? $conf['graphSenderUserId']
                : ($graphMessage['from']
                    ?? (!empty($conf['fromEmail']) ? $conf['fromEmail'] : null)
                    ?? $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']
                    ?? '');

            if (empty($graphSenderUserId)) {
                throw new \RuntimeException('No Microsoft Graph sender user ID could be resolved. Configure graphSenderUserId, fromEmail, or TYPO3 MAIL.defaultMailFromAddress.');
            }

            $requestBody = new SendMailPostRequestBody();
            $requestBody->setMessage($graphMessage['message']);
            $requestBody->setSaveToSentItems($saveToSentItems);

            // Send the email using Microsoft Graph API
            $graphServiceClient->users()->byUserId($graphSenderUserId)->sendMail()->post($requestBody)->wait();
        } catch (Exception $e) {
            $this->logger->alert('Sending mail' . ($graphSenderUserId ? " via Graph sender {$graphSenderUserId}" : '') . ' failed!' . PHP_EOL . $e->getMessage());
            throw new RuntimeException('Sending mail with Exchange365 mailer failed. Please check credentials setup.' . PHP_EOL . $e->getMessage());
        }

        $this->logger->debug('Mail sent successfully with ' . self::class . ' via Graph sender ' . $graphSenderUserId);
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
