<?php

namespace OliverKroener\OkExchange365\Mail;

use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody;
use Microsoft\Kiota\Abstractions\ApiException;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Graph\GraphServiceClient;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use TYPO3\CMS\Core\Mail\MailerInterface;
use OliverKroener\Helpers\MSGraphApi\MSGraphMailApiService;

class Exchange365Mailer implements MailerInterface
{
    protected $sentMessage;
    protected $transport;

    /**
     * Constructor to initialize the Exchange 365 Mailer with the configuration settings.
     */
    public function __construct()
    {
    }

    /**
     * Sends the email using Microsoft Graph API.
     *
     * @param RawMessage $message The email message to be sent.
     * @param Envelope|null $envelope The envelope configuration, if any.
     * @throws RuntimeException If sending fails.
     */
    public function send(RawMessage $message, ?Envelope $envelope = null): void
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

        } catch (ApiException $e) {
            throw new RuntimeException('Failed to send email: ' . $e->getMessage(), 0, $e);
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

}
