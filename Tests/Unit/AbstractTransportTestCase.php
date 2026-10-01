<?php

namespace OliverKroener\OkExchange365\Tests\Unit;

use OliverKroener\OkExchange365\Mail\Transport\Exchange365Transport;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Shared plumbing for the transport unit tests (TYPO3 10 line).
 *
 * Test methods use the plain `testSomething()` naming convention and no data
 * providers. On this branch that is not a style preference but a correctness
 * requirement: the lab runs PHP 7.4, where `#[Test]` is parsed as a `#` comment.
 * An attribute-based test file would load without error and contribute ZERO tests,
 * reporting green while testing nothing.
 *
 * No PHP 8 syntax anywhere in this suite - no union types, no constructor
 * promotion, no match, no nullsafe operator, no named arguments.
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
     * This line's constructor takes only the mail settings - it has no dispatcher
     * or logger argument, and never calls parent::__construct(). That is why no
     * instance priming is needed here, unlike on the 3.x and 4.x lines.
     *
     * @param array $mailSettings
     * @return Exchange365Transport
     */
    protected function createSubject(array $mailSettings)
    {
        return new Exchange365Transport($mailSettings);
    }

    /**
     * Invoke one of the transport's private configuration methods.
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
