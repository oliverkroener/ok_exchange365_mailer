<?php

/*
 * Lab-only helper for the test matrix. Never part of the shipped extension.
 *
 * Mounted read-only into every lab at /var/www/matrix and autoloaded from there,
 * so the same code drives TYPO3 9 (SwiftMailer) through TYPO3 14 (Symfony Mailer).
 * Keep the syntax PHP 7.2 compatible: the TYPO3 9 and 10 labs run PHP 7.4.
 */

namespace OliverKroener\OkExchange365\TestFixture;

use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class MatrixMail
{
    /**
     * Send one real message through whatever MAIL.transport is configured.
     *
     * @throws \Throwable whatever the transport throws
     */
    public static function send(string $context): string
    {
        $recipient = (string)getenv('EXCHANGE365_TEST_RECIPIENT');
        $from = (string)getenv('EXCHANGE365_FROM_EMAIL');
        $run = (string)getenv('EX365_MATRIX_RUN');
        $major = (string)getenv('EX365_MATRIX_MAJOR');

        if ($recipient === '' || $from === '') {
            throw new \RuntimeException('EXCHANGE365_TEST_RECIPIENT / EXCHANGE365_FROM_EMAIL are not in the environment');
        }

        $subject = sprintf('[ex365-matrix] %s v%s %s', $run, $major, $context);
        $body = sprintf(
            "Sent by the ok_exchange365_mailer test matrix.\nRun: %s\nTYPO3 major: %s\nContext: %s\nPHP: %s\nUTC: %s\n",
            $run,
            $major,
            $context,
            PHP_VERSION,
            gmdate('c')
        );

        $mail = GeneralUtility::makeInstance(MailMessage::class);

        if (method_exists($mail, 'text')) {
            // TYPO3 10+: Symfony Mime
            $mail->from($from)->to($recipient)->subject($subject)->text($body);
            if (class_exists(Mailer::class)) {
                GeneralUtility::makeInstance(Mailer::class)->send($mail);
            } else {
                $mail->send();
            }
        } else {
            // TYPO3 9: SwiftMailer
            $mail->setFrom($from)->setTo($recipient)->setSubject($subject)->setBody($body);
            if ($mail->send() < 1) {
                throw new \RuntimeException('SwiftMailer reported 0 accepted recipients');
            }
        }

        return $subject;
    }
}
