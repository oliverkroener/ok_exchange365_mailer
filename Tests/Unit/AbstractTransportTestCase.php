<?php

namespace OliverKroener\OkExchange365\Tests\Unit;

use OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\SymfonyPsrEventDispatcherAdapter\EventDispatcherAdapter;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Shared plumbing for the transport unit tests (TYPO3 11 line).
 *
 * Test methods use the plain `testSomething()` naming convention and no data
 * providers, so this file is byte-compatible with PHPUnit 9 through 13. That is
 * deliberate: this branch runs PHPUnit 9.6, where `#[Test]` would be parsed as a
 * comment and the file would silently contribute zero tests.
 *
 * No PHP 8 syntax is used here, so the suite also runs on PHP 7.4 - which TYPO3 11
 * still supports even though the test matrix provisions its lab with PHP 8.1.
 */
abstract class AbstractTransportTestCase extends UnitTestCase
{
    protected function setUp(): void
    {
        $this->resetSingletonInstances = true;
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = [];
        unset($GLOBALS['TSFE']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TSFE']);
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * Build the transport under test.
     *
     * The constructor calls GeneralUtility::makeInstance() on the event dispatcher
     * adapter unconditionally, and that adapter has a mandatory constructor
     * argument. Without a container makeInstance() would fall through to `new` and
     * raise an ArgumentCountError, so prime the instance stack instead - it is
     * checked before the container.
     *
     * @param array $mailSettings
     * @return Exchange365Transport
     */
    protected function createSubject(array $mailSettings)
    {
        $psrDispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };

        GeneralUtility::addInstance(
            EventDispatcherAdapter::class,
            new EventDispatcherAdapter($psrDispatcher)
        );

        return new Exchange365Transport($mailSettings, null, new NullLogger());
    }

    /**
     * Invoke one of the transport's private configuration methods.
     *
     * Reflection keeps the production class untouched, and a method rename surfaces
     * as a loud ReflectionException rather than a silently skipped test.
     *
     * @param object $subject
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    protected function callPrivate($subject, $method, array $arguments = [])
    {
        $reflection = new \ReflectionMethod($subject, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($subject, $arguments);
    }

    /**
     * Install a frontend TypoScript setup.
     *
     * TYPO3 11 exposes frontend TypoScript through $GLOBALS['TSFE']->tmpl->setup;
     * the "frontend.typoscript" request attribute of TYPO3 13/14 does not exist here.
     *
     * @param array $settings
     */
    protected function setTypoScript(array $settings): void
    {
        $tsfe = new \stdClass();
        $tsfe->tmpl = new \stdClass();
        $tsfe->tmpl->setup = [
            'plugin.' => [
                'tx_okexchange365mailer.' => [
                    'settings.' => [
                        'exchange365.' => $settings,
                    ],
                ],
            ],
        ];

        $GLOBALS['TSFE'] = $tsfe;
    }

    /**
     * Install a raw TypoScript setup array (for the "branch missing" cases).
     *
     * @param array $setup
     */
    protected function setRawTypoScript(array $setup): void
    {
        $tsfe = new \stdClass();
        $tsfe->tmpl = new \stdClass();
        $tsfe->tmpl->setup = $setup;

        $GLOBALS['TSFE'] = $tsfe;
    }
}
