<?php

namespace OliverKroener\OkExchange365\Mail;

use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Mail\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\MessageParser;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Microsoft\Graph\Graph;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Abstractions\ApiException;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody;
use Microsoft\Graph\Generated\Models\BodyType;
use Microsoft\Graph\Generated\Models\EmailAddress;
use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailRequestBuilder;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Message;
use Microsoft\Graph\Generated\Models\Recipient;
use GuzzleHttp\Client;
use Microsoft\Graph\Generated\Models\FileAttachment;
use RuntimeException;
use GuzzleHttp\Psr7\Utils;

use Symfony\Component\Mime\Email;

class Exchange365Mailer implements MailerInterface
{
    protected string $tenantId;
    protected string $clientId;
    protected string $clientSecretId;
    protected string $clientSecret;
    protected string $fromEmail;
    protected $sentMessage;
    protected $transport;

    public function __construct()
    {
        $conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_okexchange365mailer.']['settings.']['exchange365.'];

        $this->tenantId = $conf['tenantId'];
        $this->clientId = $conf['clientId'];
        $this->clientSecretId = $conf['clientSecretId'];
        $this->clientSecret = $conf['clientSecret'];
        $this->fromEmail = $conf['fromEmail'];
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $tokenRequestContext = new ClientCredentialContext(
            $this->tenantId,
            $this->clientId,
            $this->clientSecret
        );

        $graphServiceClient = new GraphServiceClient($tokenRequestContext);

        try {
            // Convert to Microsoft Graph message format
            $graphMessage = $this->convertToGraphMessage($message);

            $requestBody = new SendMailPostRequestBody();
            $requestBody->setMessage($graphMessage);

            // Generate the filename using the current date and time
            $filename = "../messages/" . date("Y-m-d_H:i:s") . "-message.txt";

            // Open the file for writing ('w' mode)
            // The 'w' mode opens the file for writing only; it places the file pointer at the beginning of the file
            // and truncates the file to zero length. If the file does not exist, it attempts to create it.
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

            // Create SendMailRequestBuilder to send the email
            $graphServiceClient->users()->byUserId($this->fromEmail)->sendMail()->post($requestBody)->wait();

            $t = 1;
            //->sendMail()->post($requestBody)->wait();    
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to send email: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getSentMessage(): ?SentMessage
    {
        return $this->sentMessage;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function getRealTransport(): TransportInterface
    {
        return $this->transport; // Assuming you are using the same transport; customize if necessary
    }

    /**
     * Converts parsed email data into a Microsoft Graph-compatible message object.
     *
     * @param RawMessage $rawMessage
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

        // Create the attachments array
        /*$attachmentsArray = [];
        foreach ($attachments as $attachment) {
            $fileAttachment = new FileAttachment();
            $fileAttachment->setName($attachment['name']);
            $fileAttachment->setContentBytes(base64_encode($attachment['content']));
            $attachmentsArray[] = $fileAttachment;
        }*/

        // set the "From" address to sender
        $from = new Recipient();
        $fromEmail = new EmailAddress();
        $fromEmail->setAddress($this->fromEmail); // Use the parsed 'From' header or a default value
        $from->setEmailAddress($fromEmail);

        // Set the "ReplyTo" address to sender of the email
        $replyToFromSender = $message->getHeader('From');
        $replyToFromSenderParts = $replyToFromSender->getAllParts();
        if ($replyToFromSender->getValue() !== '') {
            $replyTo = new Recipient();
            $replyToEmail = new EmailAddress();
            $replyToEmail->setAddress($replyToFromSenderParts[0]->getValue());
            if ($replyToFromSender->getName() !== null) {
                $replyToEmail->setName($replyToFromSenderParts[0]->getName());
            }
            $replyTo->setEmailAddress($replyToEmail);
        }

        // Process attachments
        $fileAttachments = [];
        $attachments = $message->getAllAttachmentParts();
        foreach ($attachments as $attachment) {
            $attachmentName = $attachment->getFilename();
            $attachmentContentType = $attachment->getContentType(); // Retrieves the content type
            $attachmentContent = $attachment->getContent(); // Retrieves the attachment content
        
            $fileAttachment = new FileAttachment();
            $fileAttachment->setName($attachmentName);
            $fileAttachment->setContentType($attachmentContentType);

            // Assuming your content is stored in $content
            $stream = Utils::streamFor(base64_encode($attachmentContent));
            $fileAttachment->setContentBytes($stream);

            $fileAttachments[] = $fileAttachment;
        }
        // Construct the message object
        $graphMessage = new Message();
        $graphMessage->setFrom($from);
        $graphMessage->setToRecipients($toRecipientsArray);
        // $graphMessage->setReplyTo($replyTo);
        $graphMessage->setSubject($message->getSubject() ?? 'No Subject');
        $graphMessage->setBody($body);
        $graphMessage->setAttachments($fileAttachments);

        // Set the "To" recipients

        // Set the attachments
        // $message->setAttachments($attachmentsArray);

        return $graphMessage;
    }

    /**
     * Parses an email address string into display name and address components.
     *
     * @param string $email The email string in the format 'Name <email@domain.com>'.
     * @return array An associative array with 'name' and 'address'.
     */
    function parseEmailAddress($email)
    {
        // Use regex to extract the name and email address
        if (preg_match('/^(.*)<(.*)>$/', $email, $matches)) {
            $name = trim($matches[1]);
            $address = trim($matches[2]);
        } else {
            // If no name is provided, just use the email address
            $name = null;
            $address = trim($email);
        }

        return [
            'name' => $name,
            'address' => $address,
        ];
    }
}
