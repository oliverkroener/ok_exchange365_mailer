<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Tests\Functional\Configuration;

use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The site set is the preferred frontend configuration path on TYPO3 13 and 14.
 *
 * Site sets do not exist before TYPO3 13, so every test here skips on 12 with a
 * stated reason rather than silently passing.
 */
final class SiteSetRegistrationTest extends FunctionalTestCase
{
    private const SET_NAME = 'oliverkroener/ok-exchange365-mailer';

    protected array $testExtensionsToLoad = [
        'oliverkroener/ok-exchange365-mailer',
    ];

    private function skipWithoutSetRegistry(): void
    {
        if (!class_exists(\TYPO3\CMS\Core\Site\Set\SetRegistry::class)) {
            self::markTestSkipped('Site sets require TYPO3 13 or newer.');
        }
    }

    private function setDirectory(): string
    {
        return __DIR__ . '/../../../Configuration/Sets/Exchange365Mailer/';
    }

    public function testSetConfigurationFilesExist(): void
    {
        self::assertFileExists($this->setDirectory() . 'config.yaml');
        self::assertFileExists($this->setDirectory() . 'settings.definitions.yaml');
        self::assertFileExists($this->setDirectory() . 'setup.typoscript');
    }

    public function testSetIsNamedAfterTheComposerPackage(): void
    {
        $config = Yaml::parseFile($this->setDirectory() . 'config.yaml');

        self::assertSame(self::SET_NAME, $config['name'] ?? null);
    }

    public function testSetIsRegisteredInTheSetRegistry(): void
    {
        $this->skipWithoutSetRegistry();

        $registry = $this->get(\TYPO3\CMS\Core\Site\Set\SetRegistry::class);

        self::assertTrue(
            $registry->hasSet(self::SET_NAME),
            sprintf('Site set "%s" is not registered.', self::SET_NAME)
        );
    }

    public function testEverySettingDefinitionMatchesATypoScriptConstant(): void
    {
        // The site-setting keys are named identically to the TypoScript constants on
        // purpose - that is what lets the set reuse the static template's mapping
        // file verbatim. Drift here breaks the set silently.
        $definitions = Yaml::parseFile($this->setDirectory() . 'settings.definitions.yaml');
        $settings = $definitions['settings'] ?? [];

        self::assertNotEmpty($settings, 'No settings found in settings.definitions.yaml');

        $constants = (string)file_get_contents(
            __DIR__ . '/../../../Configuration/TypoScript/constants.typoscript'
        );

        foreach (array_keys($settings) as $key) {
            $leaf = substr((string)$key, (int)strrpos((string)$key, '.') + 1);
            self::assertStringContainsString(
                $leaf,
                $constants,
                sprintf('Site setting "%s" has no matching TypoScript constant.', $key)
            );
        }
    }

    public function testSaveToSentItemsIsDeclaredAsBoolean(): void
    {
        // This is the setting that is exempt from the empty-value guard in
        // getConfiguration(), precisely because a bool false flattens to an empty
        // constant. If the type ever changes, that exemption stops making sense.
        $definitions = Yaml::parseFile($this->setDirectory() . 'settings.definitions.yaml');
        $settings = $definitions['settings'] ?? [];

        $found = null;
        foreach ($settings as $key => $definition) {
            if (str_ends_with((string)$key, 'saveToSentItems')) {
                $found = $definition;
                break;
            }
        }

        self::assertNotNull($found, 'saveToSentItems is not declared in the set.');
        self::assertSame('bool', $found['type'] ?? null);
    }
}
