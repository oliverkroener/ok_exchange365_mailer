<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Tests\Unit\Lowlevel\EventListener;

use OliverKroener\OkExchange365\Lowlevel\EventListener\ModifyBlindedConfigurationOptionsEventListener;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers credential blinding in the backend Configuration module.
 *
 * modifyBlindedConfigurationOptions() is public, so no reflection is needed here.
 */
final class ModifyBlindedConfigurationOptionsEventListenerTest extends UnitTestCase
{
    private ModifyBlindedConfigurationOptionsEventListener $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->createListener([]);
        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = [];
    }

    /**
     * @param array<string, Site> $sites
     */
    private function createListener(array $sites): ModifyBlindedConfigurationOptionsEventListener
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn($sites);

        return new ModifyBlindedConfigurationOptionsEventListener($siteFinder);
    }

    /**
     * @return array<string, mixed>
     */
    private function blind(): array
    {
        return $this->subject->modifyBlindedConfigurationOptions([]);
    }

    public function testBlindsAllThreeCredentialsKeepingTwoCharactersAtEachEnd(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = [
            'transport_exchange365_tenantId' => 'abcdefghij',
            'transport_exchange365_clientId' => 'klmnopqrst',
            'transport_exchange365_clientSecret' => 'uvwxyz1234',
        ];

        $blinded = $this->blind()['TYPO3_CONF_VARS']['MAIL'];

        self::assertSame('ab******ij', $blinded['transport_exchange365_tenantId']);
        self::assertSame('kl******st', $blinded['transport_exchange365_clientId']);
        self::assertSame('uv******34', $blinded['transport_exchange365_clientSecret']);
    }

    public function testLeavesUnsetCredentialsUntouched(): void
    {
        $result = $this->blind();

        self::assertArrayNotHasKey('TYPO3_CONF_VARS', $result);
    }

    public function testLeavesEmptyCredentialsUntouched(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = [
            'transport_exchange365_tenantId' => '',
            'transport_exchange365_clientId' => 'klmnopqrst',
        ];

        $blinded = $this->blind()['TYPO3_CONF_VARS']['MAIL'];

        self::assertArrayNotHasKey('transport_exchange365_tenantId', $blinded);
        self::assertSame('kl******st', $blinded['transport_exchange365_clientId']);
    }

    public function testHandlesMultibyteCredentialsWithoutCorruption(): void
    {
        // mb_substr, not substr: splitting a multibyte character would emit
        // invalid UTF-8 into the backend module.
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_tenantId'] = 'äöüßéèêëaz';

        $blinded = $this->blind()['TYPO3_CONF_VARS']['MAIL'];

        self::assertSame('äö******az', $blinded['transport_exchange365_tenantId']);
        self::assertTrue(mb_check_encoding($blinded['transport_exchange365_tenantId'], 'UTF-8'));
    }

    public function testCastsNonStringCredentialValuesBeforeBlinding(): void
    {
        // Configuration is operator-supplied, so a value can arrive as an int. The
        // (string) cast is what keeps mb_substr() from raising a TypeError - and
        // neither PHPStan at level 8 nor php-cs-fixer would catch its removal.
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_tenantId'] = 1234567890;

        $blinded = $this->blind()['TYPO3_CONF_VARS']['MAIL'];

        self::assertSame('12******90', $blinded['transport_exchange365_tenantId']);
    }

    public function testPreservesUnrelatedBlindedOptions(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_tenantId'] = 'abcdefghij';

        $result = $this->subject->modifyBlindedConfigurationOptions([
            'TYPO3_CONF_VARS' => [
                'MAIL' => ['transport_smtp_password' => '********'],
                'DB' => ['password' => '********'],
            ],
        ]);

        self::assertSame('********', $result['TYPO3_CONF_VARS']['MAIL']['transport_smtp_password']);
        self::assertSame('********', $result['TYPO3_CONF_VARS']['DB']['password']);
        self::assertSame('ab******ij', $result['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_tenantId']);
    }

    public function testShortCredentialsStillProduceAMaskedValue(): void
    {
        // Pins the behaviour for a value shorter than the four characters the mask
        // keeps: the result must not leak more than it hides.
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_tenantId'] = 'abc';

        $blinded = $this->blind()['TYPO3_CONF_VARS']['MAIL'];

        self::assertStringContainsString('******', $blinded['transport_exchange365_tenantId']);
    }

    // ------------------------------------------------------------- site settings

    public function testBlindsNestedSiteSettingCredentials(): void
    {
        $site = new Site('main', 1, ['settings' => ['plugin' => ['tx_okexchange365mailer' => ['settings' => ['exchange365' => [
            'clientSecret' => 'abcdefghij',
            'fromEmail' => 'from@example.org',
        ]]]]]]);

        $result = $this->createListener(['main' => $site])->modifyBlindedSiteConfigurationOptions([]);
        $exchange365 = $result['main']['settings']['plugin']['tx_okexchange365mailer']['settings']['exchange365'];

        self::assertSame('ab******ij', $exchange365['clientSecret']);
        self::assertArrayNotHasKey('fromEmail', $exchange365, 'fromEmail is not a secret');
    }

    public function testBlindsDottedSiteSettingCredentials(): void
    {
        $site = new Site('main', 1, ['settings' => [
            'plugin.tx_okexchange365mailer.settings.exchange365.tenantId' => 'abcdefghij',
        ]]);

        $result = $this->createListener(['main' => $site])->modifyBlindedSiteConfigurationOptions([]);

        self::assertSame(
            'ab******ij',
            $result['main']['settings']['plugin.tx_okexchange365mailer.settings.exchange365.tenantId']
        );
    }

    public function testSitesWithoutCredentialsAreLeftAlone(): void
    {
        $site = new Site('main', 1, ['settings' => ['other' => 'value']]);

        self::assertSame([], $this->createListener(['main' => $site])->modifyBlindedSiteConfigurationOptions([]));
    }
}
