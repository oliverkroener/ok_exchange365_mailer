<?php

namespace OliverKroener\OkExchange365\Tests\Unit\Hook;

use OliverKroener\OkExchange365\Hook\BlindedConfigurationOptionsHook;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Covers credential blinding in the backend Configuration module (TYPO3 11 line).
 *
 * TYPO3 11 has no ModifyBlindedConfigurationOptionsEvent, so this line registers a
 * hook on ConfigurationController instead of a PSR-14 listener. The blinding
 * behaviour itself is the same as on the 4.x line.
 *
 * One deliberate difference from that line: this implementation passes the raw
 * configuration value to mb_substr() without a (string) cast, so a non-string
 * credential is NOT supported here. That is asserted rather than glossed over.
 */
final class BlindedConfigurationOptionsHookTest extends UnitTestCase
{
    /**
     * @var BlindedConfigurationOptionsHook
     */
    protected $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new BlindedConfigurationOptionsHook();
        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = [];
    }

    /**
     * @return array
     */
    private function blind()
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
        self::assertArrayNotHasKey('TYPO3_CONF_VARS', $this->blind());
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
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_tenantId'] = 'äöüßéèêëaz';

        $blinded = $this->blind()['TYPO3_CONF_VARS']['MAIL'];

        self::assertSame('äö******az', $blinded['transport_exchange365_tenantId']);
        self::assertTrue(mb_check_encoding($blinded['transport_exchange365_tenantId'], 'UTF-8'));
    }

    public function testPreservesUnrelatedBlindedOptions(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_tenantId'] = 'abcdefghij';

        $result = $this->subject->modifyBlindedConfigurationOptions([
            'TYPO3_CONF_VARS' => [
                'MAIL' => ['transport_smtp_password' => '********'],
            ],
        ]);

        self::assertSame('********', $result['TYPO3_CONF_VARS']['MAIL']['transport_smtp_password']);
        self::assertSame('ab******ij', $result['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_tenantId']);
    }
}
