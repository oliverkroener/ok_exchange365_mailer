<?php

namespace OliverKroener\OkExchange365\Mail\Transport;

use Exception;
use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody;
use Microsoft\Kiota\Abstractions\ApiException;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Graph\GraphServiceClient;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use OliverKroener\Helpers\MSGraphApi\MSGraphMailApiService;
use Psr\Log\LoggerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;

class Exchange365Transport implements TransportInterface
{
    private $sentMessage;
    private ?EventDispatcherInterface $dispatcher;
    private LoggerInterface $logger;

    public function  __construct(array $mailSettings)
    {
        $this->dispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);

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
            $conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'];

            $confFromEmail = $conf['fromEmail'];

            $tokenRequestContext = new ClientCredentialContext(
                $conf['tenantId'],
                $conf['clientId'],
                $conf['clientSecret']
            );

            $graphServiceClient = new GraphServiceClient($tokenRequestContext);

            // Convert to Microsoft Graph message format
            $graphMessage = MSGraphMailApiService::convertToGraphMessage($message, $confFromEmail);

            $requestBody = new SendMailPostRequestBody();
            $requestBody->setMessage($graphMessage);

            // Send the email using Microsoft Graph API
            $graphServiceClient->users()->byUserId($confFromEmail)->sendMail()->post($requestBody)->wait();

        } catch (Exception $e) {
            $this->logger->alert('Sending mail from ' . $confFromEmail . ' failed!');
            return null;
        }

        $this->logger->debug('Mail sent successfully with ' . self::class);

        if (!$envelope) {
            $sentMessage = null;
        } else {
            $sentMessage = new SentMessage($message, $envelope);
        }

        return $sentMessage;
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

    /**
     * Gets the current transport interface.
     *
     * @return TransportInterface The transport interface in use.
     */
    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * Gets the real transport interface.
     *
     * @return TransportInterface The real transport interface.
     */
    public function getRealTransport(): TransportInterface
    {
        return $this->transport; // Customize if a different transport is used
    }

    public function __toString(): string
    {
        return 'exchange365api';
    }
}
