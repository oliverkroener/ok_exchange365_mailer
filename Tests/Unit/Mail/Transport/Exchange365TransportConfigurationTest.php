<?php

namespace OliverKroener\OkExchange365\Tests\Unit\Mail\Transport;

use OliverKroener\OkExchange365\Tests\Unit\AbstractTransportTestCase;

/**
 * Covers the configuration resolution rules.
 *
 * These are the rules the extension's own documentation calls the part "most likely
 * to be broken by a well-meaning edit", and neither PHPStan nor php-cs-fixer can see
 * them. Everything here is reached through reflection so the production class stays
 * untouched.
 */
final class Exchange365TransportConfigurationTest extends AbstractTransportTestCase
{
    /**
     * @param object $subject
     * @return array
     */
    private function resolve($subject): array
    {
        return $this->callPrivate($subject, 'getConfiguration');
    }

    // --------------------------------------------------------- getMailSettingsConfiguration

    public function testMailSettingsConfigurationMapsTransportPrefixedKeysToShortKeys(): void
    {
        $subject = $this->createSubject([
            'transport_exchange365_tenantId' => 'tenant',
            'transport_exchange365_clientId' => 'client',
            'transport_exchange365_clientSecret' => 'secret',
            'transport_exchange365_fromEmail' => 'from@example.org',
            'transport_exchange365_graphSenderUserId' => 'graph@example.org',
            'transport_exchange365_saveToSentItems' => '1',
        ]);

        self::assertSame([
            'tenantId' => 'tenant',
            'clientId' => 'client',
            'clientSecret' => 'secret',
            'fromEmail' => 'from@example.org',
            'graphSenderUserId' => 'graph@example.org',
            'saveToSentItems' => '1',
        ], $this->callPrivate($subject, 'getMailSettingsConfiguration'));
    }

    public function testMailSettingsConfigurationReturnsEmptyStringsForUnsetKeys(): void
    {
        $conf = $this->callPrivate($this->createSubject([]), 'getMailSettingsConfiguration');

        // Empty strings rather than nulls is what forces !empty() instead of ?? in
        // the sender resolution chain. Pin it.
        foreach (['tenantId', 'clientId', 'clientSecret', 'fromEmail', 'graphSenderUserId'] as $key) {
            self::assertSame('', $conf[$key], sprintf('%s should default to an empty string', $key));
        }
    }

    public function testMailSettingsConfigurationDefaultsSaveToSentItemsToStringZero(): void
    {
        $conf = $this->callPrivate($this->createSubject([]), 'getMailSettingsConfiguration');

        self::assertSame('0', $conf['saveToSentItems']);
    }

    // ----------------------------------------------------- getTypoScriptConfiguration

    public function testTypoScriptConfigurationIsNullWithoutARequest(): void
    {
        unset($GLOBALS['TSFE']);

        self::assertNull($this->callPrivate($this->createSubject([]), 'getTypoScriptConfiguration'));
    }

    public function testTypoScriptConfigurationIsNullWhenThePluginBranchIsMissing(): void
    {
        $this->setRawTypoScript(['page.' => ['10.' => []]]);

        self::assertNull($this->callPrivate($this->createSubject([]), 'getTypoScriptConfiguration'));
    }

    public function testTypoScriptConfigurationReturnsTheExchange365SubArray(): void
    {
        // Pins the exact dotted path the site set and the static template both write to.
        $this->setTypoScript(['tenantId' => 'ts-tenant']);

        self::assertSame(
            ['tenantId' => 'ts-tenant'],
            $this->callPrivate($this->createSubject([]), 'getTypoScriptConfiguration')
        );
    }

    // ------------------------------------------------------------- getConfiguration

    public function testConfigurationUsesMailSettingsOutsideTheFrontend(): void
    {
        unset($GLOBALS['TSFE']);
        $subject = $this->createSubject(['transport_exchange365_tenantId' => 'mail-tenant']);

        self::assertSame('mail-tenant', $this->resolve($subject)['tenantId']);
    }

    public function testNonEmptyTypoScriptValueOverridesTheMailSetting(): void
    {
        $this->setTypoScript(['tenantId' => 'ts-tenant']);
        $subject = $this->createSubject(['transport_exchange365_tenantId' => 'mail-tenant']);

        self::assertSame('ts-tenant', $this->resolve($subject)['tenantId']);
    }

    public function testEmptyTypoScriptValueDoesNotShadowTheMailSetting(): void
    {
        // The guard: an empty TypoScript value means "not configured here".
        $this->setTypoScript(['tenantId' => '']);
        $subject = $this->createSubject(['transport_exchange365_tenantId' => 'mail-tenant']);

        self::assertSame('mail-tenant', $this->resolve($subject)['tenantId']);
    }

    public function testNullTypoScriptValueDoesNotShadowTheMailSetting(): void
    {
        $this->setTypoScript(['tenantId' => null]);
        $subject = $this->createSubject(['transport_exchange365_tenantId' => 'mail-tenant']);

        self::assertSame('mail-tenant', $this->resolve($subject)['tenantId']);
    }

    public function testTypoScriptStringZeroOverridesBecauseZeroIsNotAnEmptyString(): void
    {
        // The guard is !== '', not !empty(). '0' is a real configured value.
        $this->setTypoScript(['fromEmail' => '0']);
        $subject = $this->createSubject(['transport_exchange365_fromEmail' => 'mail@example.org']);

        self::assertSame('0', $this->resolve($subject)['fromEmail']);
    }

    public function testConfigurationOverlaysPerKeyAndDoesNotReplaceTheWholeArray(): void
    {
        // The single most important regression test in this suite: it is what
        // separates the 4.x behaviour from the all-or-nothing resolution the 2.x and
        // 3.x lines used before 2.2.0 / 3.2.0.
        $this->setTypoScript(['tenantId' => 'ts-tenant']);
        $subject = $this->createSubject([
            'transport_exchange365_tenantId' => 'mail-tenant',
            'transport_exchange365_clientId' => 'mail-client',
            'transport_exchange365_clientSecret' => 'mail-secret',
            'transport_exchange365_fromEmail' => 'mail@example.org',
        ]);

        $conf = $this->resolve($subject);

        self::assertSame('ts-tenant', $conf['tenantId'], 'the TypoScript value must win');
        self::assertSame('mail-client', $conf['clientId'], 'other keys must survive');
        self::assertSame('mail-secret', $conf['clientSecret'], 'other keys must survive');
        self::assertSame('mail@example.org', $conf['fromEmail'], 'other keys must survive');
    }

    public function testEmptySaveToSentItemsIsAppliedBecauseItIsExemptFromTheGuard(): void
    {
        // A site setting of false flattens to an empty TypoScript constant, and there
        // it genuinely means false - so this one key must NOT be guarded.
        $this->setTypoScript(['saveToSentItems' => '']);
        $subject = $this->createSubject(['transport_exchange365_saveToSentItems' => '1']);

        self::assertSame('', $this->resolve($subject)['saveToSentItems']);
        self::assertFalse((bool)$this->resolve($subject)['saveToSentItems']);
    }

    public function testUnknownTypoScriptKeysAreCopiedIntoTheConfiguration(): void
    {
        // There is no key whitelist. Documented so nobody adds one by accident.
        $this->setTypoScript(['somethingNew' => 'value']);

        self::assertSame('value', $this->resolve($this->createSubject([]))['somethingNew']);
    }

    // --------------------------------------------------------- validateConfiguration

    public function testValidateConfigurationPassesWithAllThreeCredentials(): void
    {
        $subject = $this->createSubject([]);

        $this->callPrivate($subject, 'validateConfiguration', [[
            'tenantId' => 'tenant',
            'clientId' => 'client',
            'clientSecret' => 'secret',
        ]]);

        self::assertTrue(true, 'validateConfiguration() returned without throwing');
    }

    public function testValidateConfigurationThrowsForMissingTenantId(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenantId');

        $this->callPrivate($this->createSubject([]), 'validateConfiguration', [[
            'clientId' => 'client',
            'clientSecret' => 'secret',
        ]]);
    }

    public function testValidateConfigurationThrowsForMissingClientId(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('clientId');

        $this->callPrivate($this->createSubject([]), 'validateConfiguration', [[
            'tenantId' => 'tenant',
            'clientSecret' => 'secret',
        ]]);
    }

    public function testValidateConfigurationThrowsForMissingClientSecret(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('clientSecret');

        $this->callPrivate($this->createSubject([]), 'validateConfiguration', [[
            'tenantId' => 'tenant',
            'clientId' => 'client',
        ]]);
    }

    public function testValidateConfigurationRejectsStringZeroAsACredential(): void
    {
        // empty('0') is true, so '0' is treated as missing. Pinned so nobody
        // "corrects" the check to === '' without weighing it.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenantId');

        $this->callPrivate($this->createSubject([]), 'validateConfiguration', [[
            'tenantId' => '0',
            'clientId' => 'client',
            'clientSecret' => 'secret',
        ]]);
    }

    // ------------------------------------------------------------------ __toString

    public function testTransportNameIsExchange365Mailer(): void
    {
        self::assertSame('exchange365mailer', (string)$this->createSubject([]));
    }
}
