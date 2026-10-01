<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Tests\Functional\Mail;

use OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport;
use TYPO3\CMS\Core\Mail\TransportFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Proves that TYPO3 really selects this transport.
 *
 * This extension registers no DSN factory: TYPO3 activates it purely because
 * MAIL.transport holds the fully-qualified class name, and the core mailer then
 * instantiates the class directly with the whole MAIL array as its argument.
 * Nothing but a booted TYPO3 can confirm that contract still holds on a given major.
 *
 * It also locks in the Services.yaml exclusion: if the transport were ever added
 * back to autowiring, the container would try to autowire `array $mailSettings` and
 * compilation would fail here, at boot.
 */
final class TransportSelectionTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverkroener/ok-exchange365-mailer',
    ];

    /**
     * @var array<string, mixed>
     */
    private array $mailSettingsBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailSettingsBackup = $GLOBALS['TYPO3_CONF_VARS']['MAIL'] ?? [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = $this->mailSettingsBackup;
        parent::tearDown();
    }

    public function testExtensionIsLoaded(): void
    {
        self::assertTrue(
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('ok_exchange365_mailer')
        );
    }

    public function testTransportClassIsAutoloadable(): void
    {
        self::assertTrue(class_exists(Exchange365Transport::class));
    }

    public function testTransportFactoryReturnsTheExchange365TransportForTheFullyQualifiedClassName(): void
    {
        $mailSettings = [
            'transport' => Exchange365Transport::class,
            'transport_exchange365_tenantId' => 'tenant',
            'transport_exchange365_clientId' => 'client',
            'transport_exchange365_clientSecret' => 'secret',
        ];

        $transport = GeneralUtility::makeInstance(TransportFactory::class)->get($mailSettings);

        self::assertInstanceOf(Exchange365Transport::class, $transport);
    }

    public function testSelectedTransportReportsItsDisplayName(): void
    {
        $transport = GeneralUtility::makeInstance(TransportFactory::class)->get([
            'transport' => Exchange365Transport::class,
        ]);

        self::assertSame('exchange365api', (string)$transport);
    }

    public function testTransportReceivesTheMailSettingsArray(): void
    {
        // The core mailer passes $GLOBALS['TYPO3_CONF_VARS']['MAIL'] straight into the
        // constructor. If that ever changes, getMailSettingsConfiguration() silently
        // sees an empty baseline - so assert the credentials actually arrive.
        $mailSettings = [
            'transport' => Exchange365Transport::class,
            'transport_exchange365_tenantId' => 'arrived-tenant',
            'transport_exchange365_clientId' => 'arrived-client',
            'transport_exchange365_clientSecret' => 'arrived-secret',
        ];

        $transport = GeneralUtility::makeInstance(TransportFactory::class)->get($mailSettings);

        $method = new \ReflectionMethod($transport, 'getMailSettingsConfiguration');
        $method->setAccessible(true);
        /** @var array<string, mixed> $conf */
        $conf = $method->invoke($transport);

        self::assertSame('arrived-tenant', $conf['tenantId']);
        self::assertSame('arrived-client', $conf['clientId']);
        self::assertSame('arrived-secret', $conf['clientSecret']);
    }

    public function testTransportIsNotRegisteredAsAPublicContainerService(): void
    {
        // Configuration/Services.yaml deliberately excludes the transport from
        // autoloading, because TYPO3 constructs it itself with a scalar argument.
        self::assertFalse(
            $this->getContainer()->has(Exchange365Transport::class),
            'The transport must stay out of the DI container - see Services.yaml.'
        );
    }
}
