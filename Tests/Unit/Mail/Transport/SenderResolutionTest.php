<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Tests\Unit\Mail\Transport;

use OliverKroener\OkExchange365\Tests\Unit\AbstractTransportTestCase;

/**
 * Covers the Graph sender resolution order:
 *
 *   conf.graphSenderUserId
 *     -> the message From address
 *       -> conf.fromEmail
 *         -> MAIL.defaultMailFromAddress
 *           -> RuntimeException
 *
 * Exercises the production method Exchange365Transport::resolveGraphSenderUserId().
 */
final class SenderResolutionTest extends AbstractTransportTestCase
{
    /**
     * @param array<string, mixed> $conf
     */
    private function resolveSender(array $conf, ?string $messageFrom, ?string $defaultMailFrom): string
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = $defaultMailFrom;

        return (string)$this->callPrivate($this->createSubject([]), 'resolveGraphSenderUserId', [$conf, $messageFrom]);
    }

    public function testGraphSenderUserIdWinsOverEverythingElse(): void
    {
        // The Send As / Send On Behalf contract: the Graph mailbox is deliberately
        // decoupled from the visible From header.
        self::assertSame('graph@example.org', $this->resolveSender(
            ['graphSenderUserId' => 'graph@example.org', 'fromEmail' => 'conf@example.org'],
            'message@example.org',
            'default@example.org'
        ));
    }

    public function testMessageFromIsUsedWhenGraphSenderUserIdIsEmpty(): void
    {
        self::assertSame('message@example.org', $this->resolveSender(
            ['graphSenderUserId' => '', 'fromEmail' => 'conf@example.org'],
            'message@example.org',
            'default@example.org'
        ));
    }

    public function testConfiguredFromEmailIsUsedWhenTheMessageHasNoFrom(): void
    {
        self::assertSame('conf@example.org', $this->resolveSender(
            ['graphSenderUserId' => '', 'fromEmail' => 'conf@example.org'],
            null,
            'default@example.org'
        ));
    }

    public function testDefaultMailFromAddressIsTheLastResort(): void
    {
        self::assertSame('default@example.org', $this->resolveSender(
            ['graphSenderUserId' => '', 'fromEmail' => ''],
            null,
            'default@example.org'
        ));
    }

    public function testResolutionThrowsWhenNothingIsConfigured(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No Microsoft Graph sender user ID could be resolved');

        $this->resolveSender(['graphSenderUserId' => '', 'fromEmail' => ''], null, null);
    }

    public function testEmptyStringsAreTreatedAsUnsetRatherThanAsValues(): void
    {
        // Every step treats '' as unset, including an empty message From address.
        self::assertSame('default@example.org', $this->resolveSender(
            ['graphSenderUserId' => '', 'fromEmail' => ''],
            '',
            'default@example.org'
        ));
    }

    public function testMissingConfigurationKeysAreTreatedAsUnset(): void
    {
        self::assertSame('message@example.org', $this->resolveSender([], 'message@example.org', null));
    }
}
