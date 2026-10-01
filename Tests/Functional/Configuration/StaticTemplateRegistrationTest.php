<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Tests\Functional\Configuration;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The static TypoScript template must stay registered on every supported major.
 *
 * This is the TYPO3 12 frontend configuration path, and it is also what the site set
 * imports on 13/14 - so a typo in Configuration/TCA/Overrides/sys_template.php breaks
 * both paths at once, and nothing else in the suite would notice.
 */
final class StaticTemplateRegistrationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'oliverkroener/ok-exchange365-mailer',
    ];

    public function testStaticTemplateIsRegisteredForSysTemplate(): void
    {
        $items = $GLOBALS['TCA']['sys_template']['columns']['include_static_file']['config']['items'] ?? [];

        $found = false;
        foreach ($items as $item) {
            $value = is_array($item) ? ($item['value'] ?? $item[1] ?? '') : '';
            if (is_string($value) && str_contains($value, 'ok_exchange365_mailer')) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'The extension static template is not registered in sys_template.');
    }

    public function testConstantsAndSetupFilesExist(): void
    {
        $base = __DIR__ . '/../../../Configuration/TypoScript/';

        self::assertFileExists($base . 'constants.typoscript');
        self::assertFileExists($base . 'setup.typoscript');
    }

    public function testEverySettingIsMappedFromConstantsIntoSetup(): void
    {
        // Guards the "change all four places" rule: a constant that is declared but
        // never mapped into plugin.tx_okexchange365mailer produces a setting that is
        // editable in the backend and never reaches the transport.
        $base = __DIR__ . '/../../../Configuration/TypoScript/';
        $constants = (string)file_get_contents($base . 'constants.typoscript');
        $setup = (string)file_get_contents($base . 'setup.typoscript');

        // The constants are fully-qualified dotted paths, e.g.
        //   plugin.tx_okexchange365mailer.settings.exchange365.tenantId =
        // so match the leaf name rather than a bare identifier.
        preg_match_all(
            '/^\s*plugin\.tx_okexchange365mailer\.settings\.exchange365\.([a-zA-Z][a-zA-Z0-9_]*)\s*=/m',
            $constants,
            $matches
        );
        $declared = array_unique($matches[1] ?? []);

        self::assertNotEmpty($declared, 'No constants parsed - has the file format changed?');

        foreach ($declared as $setting) {
            self::assertStringContainsString(
                $setting,
                $setup,
                sprintf('Constant "%s" is never mapped in setup.typoscript', $setting)
            );
        }
    }
}
