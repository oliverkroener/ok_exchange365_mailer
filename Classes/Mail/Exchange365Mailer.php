<?php

namespace OliverKroener\OkExchange365\Mail;

// Sorted imports
use GuzzleHttp\Psr7\Utils;
use Microsoft\Graph\Generated\Models\BodyType;
use Microsoft\Graph\Generated\Models\EmailAddress;
use Microsoft\Graph\Generated\Models\FileAttachment;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Message;
use Microsoft\Graph\Generated\Models\Recipient;
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

class Exchange365Mailer implements MailerInterface
{
    protected string $tenantId;
    protected string $clientId;
    protected string $clientSecretId;
    protected string $clientSecret;
    protected string $fromEmail;
    protected $sentMessage;
    protected $transport;

    /**
     * Constructor to initialize the Exchange 365 Mailer with the configuration settings.
     */
    public function __construct()
    {
        $conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'];

        $this->tenantId = $conf['tenantId'];
        $this->clientId = $conf['clientId'];
        $this->clientSecretId = $conf['clientSecretId'];
        $this->clientSecret = $conf['clientSecret'];
        $this->fromEmail = $conf['fromEmail'];
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
            $tokenRequestContext = new ClientCredentialContext(
                $this->tenantId,
                $this->clientId,
                $this->clientSecret
            );
    
            $graphServiceClient = new GraphServiceClient($tokenRequestContext);

            // Convert to Microsoft Graph message format
            $graphMessage = $this->convertToGraphMessage($message);

            $requestBody = new SendMailPostRequestBody();
            $requestBody->setMessage($graphMessage);

            // Generate the filename using the current date and time
            $filename = "../messages/" . date("Y-m-d_H:i:s") . "-message.txt";

            // Open the file for writing ('w' mode)
            $fileHandle = fopen($filename, 'w');

            if ($fileHandle) {
                // Write the content to the file
                fwrite($fileHandle, $message->toString());

                // Close the file
                fclose($fileHandle);

                echo "Message written successfully to $filename";
            } else {
                echo "Failed to open the file for writing.";
            }

            // Send the email using Microsoft Graph API
            $graphServiceClient->users()->byUserId($this->fromEmail)->sendMail()->post($requestBody)->wait();

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

    /**
     * Converts a parsed email data into a Microsoft Graph-compatible message object.
     *
     * @param RawMessage $rawMessage The raw message to convert.
     * @return Message Microsoft Graph-compatible message.
     */
    function convertToGraphMessage(RawMessage $rawMessage): Message
    {
        // Create an Email object from the parsed MimeMessage
        $message = \ZBateson\MailMimeParser\Message::from($rawMessage->toString(), false);

        $toRecipients = $message->getHeader('To');

        foreach ($toRecipients->getAllParts() as $email) {
            $recipient = new Recipient();
            $emailAddress = new EmailAddress();
            $emailAddress->setAddress($email->getValue());
            $emailAddress->setName($email->getName());
            $recipient->setEmailAddress($emailAddress);
            $toRecipientsArray[] = $recipient;
        }

        $htmlBody = $message->getHtmlContent();
        $plainTextBody = $message->getTextContent();

        // Create the body content
        $body = new ItemBody();
        if (!empty($htmlBody)) {
            $body->setContentType(new BodyType(BodyType::HTML));
            $body->setContent($htmlBody);
        } elseif (!empty($plainTextBody)) {
            $body->setContentType(new BodyType(BodyType::TEXT));
            $body->setContent($plainTextBody);
        } else {
            $body->setContentType(new BodyType(BodyType::TEXT));
            $body->setContent(''); // Default empty content if none provided
        }

        // Process attachments
        $fileAttachments = [];
        $attachments = $message->getAllAttachmentParts();
        foreach ($attachments as $attachment) {
            $attachmentName = $attachment->getFilename();
            $attachmentContentType = $attachment->getContentType();
            $attachmentContent = $attachment->getContent();
        
            $fileAttachment = new FileAttachment();
            $fileAttachment->setName($attachmentName);
            $fileAttachment->setContentType($attachmentContentType);

            // Assuming your content is stored in $content
            $stream = Utils::streamFor(base64_encode($attachmentContent));
            $fileAttachment->setContentBytes($stream);

            $fileAttachments[] = $fileAttachment;
        }

        // Set the "From" address
        $from = new Recipient();
        $fromEmail = new EmailAddress();
        $fromEmail->setAddress($this->fromEmail); // Use the parsed 'From' header or a default value
        $from->setEmailAddress($fromEmail);

        // Construct the message object
        $graphMessage = new Message();
        $graphMessage->setFrom($from);
        $graphMessage->setToRecipients($toRecipientsArray);
        $graphMessage->setSubject($message->getSubject() ?? 'No Subject');
        $graphMessage->setBody($body);
        $graphMessage->setAttachments($fileAttachments);

        return $graphMessage;
    }
}
