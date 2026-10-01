<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Tests\Functional\Lowlevel;

use OliverKroener\OkExchange365\Lowlevel\EventListener\ModifyBlindedConfigurationOptionsEventListener;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Proves the blinding listener is actually wired, not merely present.
 *
 * The unit test covers what the listener does; only a booted container can show that
 * the event.listener tag in Configuration/Services.yaml reaches the real event.
 *
 * The event class ships with typo3/cms-lowlevel, which the extension does not
 * require - so this skips with a reason where that system extension is absent
 * rather than fataling.
 */
final class BlindedConfigurationOptionsTest extends FunctionalTestCase
{
    private const EVENT_CLASS = \TYPO3\CMS\Lowlevel\Event\ModifyBlindedConfigurationOptionsEvent::class;

    protected array $testExtensionsToLoad = [
        'oliverkroener/ok-exchange365-mailer',
    ];

    protected array $coreExtensionsToLoad = [
        'lowlevel',
    ];

    private function skipWithoutLowlevel(): void
    {
        if (!class_exists(self::EVENT_CLASS)) {
            self::markTestSkipped('typo3/cms-lowlevel is not installed in this lab.');
        }
    }

    public function testListenerClassIsAutoloadable(): void
    {
        self::assertTrue(class_exists(ModifyBlindedConfigurationOptionsEventListener::class));
    }

    public function testDispatchingTheEventBlindsTheCredentials(): void
    {
        $this->skipWithoutLowlevel();

        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_clientSecret'] = 'abcdefghij';

        $eventClass = self::EVENT_CLASS;
        $event = new $eventClass([], 'confVars');

        $this->get(EventDispatcherInterface::class)->dispatch($event);

        $options = $event->getBlindedConfigurationOptions();

        self::assertSame(
            'ab******ij',
            $options['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_clientSecret'] ?? null,
            'The listener is not wired - check the event.listener tag in Services.yaml.'
        );
    }

    public function testListenerIgnoresOtherConfigurationProviders(): void
    {
        $this->skipWithoutLowlevel();

        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_clientSecret'] = 'abcdefghij';

        $eventClass = self::EVENT_CLASS;
        $event = new $eventClass([], 'someOtherProvider');

        $this->get(EventDispatcherInterface::class)->dispatch($event);

        self::assertSame([], $event->getBlindedConfigurationOptions());
    }
}
