<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Tests\Unit;

use OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport;
use OliverKroener\OkExchange365\Tests\Unit\Fixtures\FrontendTypoScriptStub;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Adapter\EventDispatcherAdapter;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Shared plumbing for the transport unit tests.
 *
 * Test methods across this suite use the plain `testSomething()` naming convention
 * and no data providers. That is deliberate rather than old-fashioned: the suite is
 * backported to branches running PHPUnit 9.6 on PHP 7.4, where `#[Test]` is parsed
 * as a `#` comment - an attribute-based file would load cleanly and contribute zero
 * tests, reporting green while testing nothing.
 */
abstract class AbstractTransportTestCase extends UnitTestCase
{
    protected function setUp(): void
    {
        $this->resetSingletonInstances = true;
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = [];
    }

    protected function tearDown(): void
    {
        // Do not rely on backupGlobals alone: a test that fails mid-way can leave a
        // request in place, and the next test's getTypoScriptConfiguration() would
        // then read it.
        unset($GLOBALS['TYPO3_REQUEST']);
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * Build the transport under test.
     *
     * Exchange365Transport::__construct() calls
     * GeneralUtility::makeInstance(EventDispatcherAdapter::class) when no dispatcher
     * is passed, and that adapter has a mandatory constructor argument. In a unit test there is
     * no container, so makeInstance() would fall through to `new` and raise an
     * ArgumentCountError. addInstance() is the supported escape hatch: makeInstance()
     * checks the instance stack before the container.
     *
     * An explicit NullLogger is passed so makeInstance(LogManager::class) is never
     * reached either.
     *
     * @param array<string, mixed> $mailSettings
     */
    protected function createSubject(array $mailSettings): Exchange365Transport
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
     * Reflection keeps the production class untouched. A method rename surfaces as a
     * loud ReflectionException rather than a silently skipped test.
     *
     * @param array<int, mixed> $arguments
     */
    protected function callPrivate(object $subject, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($subject, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($subject, $arguments);
    }

    /**
     * A frontend request carrying the given TypoScript setup array.
     *
     * @param array<string, mixed>|null $setup
     */
    protected function frontendRequest(?array $setup): ServerRequest
    {
        $request = (new ServerRequest())->withAttribute('applicationType', 1);

        if ($setup !== null) {
            $request = $request->withAttribute(
                'frontend.typoscript',
                new FrontendTypoScriptStub($setup)
            );
        }

        return $request;
    }

    /**
     * Wrap exchange365 settings into the nested TypoScript path the transport reads.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    protected function typoScriptSetup(array $settings): array
    {
        return [
            'plugin.' => [
                'tx_okexchange365mailer.' => [
                    'settings.' => [
                        'exchange365.' => $settings,
                    ],
                ],
            ],
        ];
    }
}
