<?php

namespace OliverKroener\OkExchange365\Tests\Functional\Mail;

use OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport;
use TYPO3\CMS\Core\Mail\TransportFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Proves that TYPO3 10 really selects this transport.
 *
 * The extension registers no DSN factory: TYPO3 activates it purely because
 * MAIL.transport holds the fully-qualified class name, and the core mailer then
 * instantiates the class directly with the whole MAIL array as its argument.
 *
 * The property below is deliberately UNTYPED: this line runs on a
 * typo3/testing-framework whose FunctionalTestCase declares it without a type, and
 * redeclaring it as `array` would be a fatal property-type mismatch.
 */
final class TransportSelectionTest extends FunctionalTestCase
{
    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/ok_exchange365_mailer',
    ];

    public function testExtensionIsLoaded(): void
    {
        self::assertTrue(ExtensionManagementUtility::isLoaded('ok_exchange365_mailer'));
    }

    public function testTransportClassIsAutoloadable(): void
    {
        self::assertTrue(class_exists(Exchange365Transport::class));
    }

    public function testTransportFactoryReturnsTheExchange365Transport(): void
    {
        $transport = GeneralUtility::makeInstance(TransportFactory::class)->get([
            'transport' => Exchange365Transport::class,
            'transport_exchange365_tenantId' => 'tenant',
            'transport_exchange365_clientId' => 'client',
            'transport_exchange365_clientSecret' => 'secret',
        ]);

        self::assertInstanceOf(Exchange365Transport::class, $transport);
    }

    public function testSelectedTransportReportsItsDisplayName(): void
    {
        $transport = GeneralUtility::makeInstance(TransportFactory::class)->get([
            'transport' => Exchange365Transport::class,
        ]);

        self::assertSame('exchange365mailer', (string)$transport);
    }

    public function testTransportReceivesTheMailSettingsArray(): void
    {
        $transport = GeneralUtility::makeInstance(TransportFactory::class)->get([
            'transport' => Exchange365Transport::class,
            'transport_exchange365_tenantId' => 'arrived-tenant',
            'transport_exchange365_clientId' => 'arrived-client',
            'transport_exchange365_clientSecret' => 'arrived-secret',
        ]);

        $method = new \ReflectionMethod($transport, 'getMailSettingsConfiguration');
        $method->setAccessible(true);
        $conf = $method->invoke($transport);

        self::assertSame('arrived-tenant', $conf['tenantId']);
        self::assertSame('arrived-client', $conf['clientId']);
        self::assertSame('arrived-secret', $conf['clientSecret']);
    }
}
