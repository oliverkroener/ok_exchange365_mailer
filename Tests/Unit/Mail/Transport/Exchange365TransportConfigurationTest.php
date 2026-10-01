<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Tests\Unit\Mail\Transport;

use OliverKroener\OkExchange365\Tests\Unit\AbstractTransportTestCase;
use TYPO3\CMS\Core\Http\ServerRequest;

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
     * @return array<string, mixed>
     */
    private function resolve(object $subject): array
    {
        /** @var array<string, mixed> $conf */
        $conf = $this->callPrivate($subject, 'getConfiguration');

        return $conf;
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
        unset($GLOBALS['TYPO3_REQUEST']);

        self::assertNull($this->callPrivate($this->createSubject([]), 'getTypoScriptConfiguration'));
    }

    public function testTypoScriptConfigurationIsNullInTheBackend(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('applicationType', 2);

        self::assertNull($this->callPrivate($this->createSubject([]), 'getTypoScriptConfiguration'));
    }

    public function testTypoScriptConfigurationIsNullForACombinedApplicationType(): void
    {
        // The check is a strict !== 1, so an OR'ed request type such as
        // REQUESTTYPE_FE|REQUESTTYPE_AJAX (9) falls back to the mail settings.
        // Documented here so the behaviour is a decision, not a surprise.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('applicationType', 9);

        self::assertNull($this->callPrivate($this->createSubject([]), 'getTypoScriptConfiguration'));
    }

    public function testTypoScriptConfigurationIsNullWhenTheFrontendTypoScriptAttributeIsAbsent(): void
    {
        // This is the TYPO3 12.4.0 path: that release predates the
        // "frontend.typoscript" request attribute, so the baseline must stand.
        $GLOBALS['TYPO3_REQUEST'] = $this->frontendRequest(null);

        self::assertNull($this->callPrivate($this->createSubject([]), 'getTypoScriptConfiguration'));
    }

    public function testTypoScriptConfigurationIsNullWhenThePluginBranchIsMissing(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = $this->frontendRequest(['page.' => ['10.' => []]]);

        self::assertNull($this->callPrivate($this->createSubject([]), 'getTypoScriptConfiguration'));
    }

    public function testTypoScriptConfigurationReturnsTheExchange365SubArray(): void
    {
        // Pins the exact dotted path the site set and the static template both write to.
        $GLOBALS['TYPO3_REQUEST'] = $this->frontendRequest(
            $this->typoScriptSetup(['tenantId' => 'ts-tenant'])
        );

        self::assertSame(
            ['tenantId' => 'ts-tenant'],
            $this->callPrivate($this->createSubject([]), 'getTypoScriptConfiguration')
        );
    }

    // ------------------------------------------------------------- getConfiguration

    public function testConfigurationUsesMailSettingsOutsideTheFrontend(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        $subject = $this->createSubject(['transport_exchange365_tenantId' => 'mail-tenant']);

        self::assertSame('mail-tenant', $this->resolve($subject)['tenantId']);
    }

    public function testNonEmptyTypoScriptValueOverridesTheMailSetting(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = $this->frontendRequest(
            $this->typoScriptSetup(['tenantId' => 'ts-tenant'])
        );
        $subject = $this->createSubject(['transport_exchange365_tenantId' => 'mail-tenant']);

        self::assertSame('ts-tenant', $this->resolve($subject)['tenantId']);
    }

    public function testEmptyTypoScriptValueDoesNotShadowTheMailSetting(): void
    {
        // The guard: an empty TypoScript value means "not configured here".
        $GLOBALS['TYPO3_REQUEST'] = $this->frontendRequest(
            $this->typoScriptSetup(['tenantId' => ''])
        );
        $subject = $this->createSubject(['transport_exchange365_tenantId' => 'mail-tenant']);

        self::assertSame('mail-tenant', $this->resolve($subject)['tenantId']);
    }

    public function testNullTypoScriptValueDoesNotShadowTheMailSetting(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = $this->frontendRequest(
            $this->typoScriptSetup(['tenantId' => null])
        );
        $subject = $this->createSubject(['transport_exchange365_tenantId' => 'mail-tenant']);

        self::assertSame('mail-tenant', $this->resolve($subject)['tenantId']);
    }

    public function testTypoScriptStringZeroOverridesBecauseZeroIsNotAnEmptyString(): void
    {
        // The guard is !== '', not !empty(). '0' is a real configured value.
        $GLOBALS['TYPO3_REQUEST'] = $this->frontendRequest(
            $this->typoScriptSetup(['fromEmail' => '0'])
        );
        $subject = $this->createSubject(['transport_exchange365_fromEmail' => 'mail@example.org']);

        self::assertSame('0', $this->resolve($subject)['fromEmail']);
    }

    public function testConfigurationOverlaysPerKeyAndDoesNotReplaceTheWholeArray(): void
    {
        // The single most important regression test in this suite: it is what
        // separates the 4.x behaviour from the all-or-nothing resolution the 2.x and
        // 3.x lines used before 2.2.0 / 3.2.0.
        $GLOBALS['TYPO3_REQUEST'] = $this->frontendRequest(
            $this->typoScriptSetup(['tenantId' => 'ts-tenant'])
        );
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
        $GLOBALS['TYPO3_REQUEST'] = $this->frontendRequest(
            $this->typoScriptSetup(['saveToSentItems' => ''])
        );
        $subject = $this->createSubject(['transport_exchange365_saveToSentItems' => '1']);

        self::assertSame('', $this->resolve($subject)['saveToSentItems']);
        self::assertFalse((bool)$this->resolve($subject)['saveToSentItems']);
    }

    public function testUnknownTypoScriptKeysAreCopiedIntoTheConfiguration(): void
    {
        // There is no key whitelist. Documented so nobody adds one by accident.
        $GLOBALS['TYPO3_REQUEST'] = $this->frontendRequest(
            $this->typoScriptSetup(['somethingNew' => 'value'])
        );

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

    public function testTransportNameIsExchange365Api(): void
    {
        self::assertSame('exchange365api', (string)$this->createSubject([]));
    }
}
