<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Lowlevel\EventListener;

use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Lowlevel\Event\ModifyBlindedConfigurationOptionsEvent;

final class ModifyBlindedConfigurationOptionsEventListener
{
    /**
     * @var list<string>
     */
    private const BLINDED_MAIL_SETTINGS = [
        'transport_exchange365_clientId',
        'transport_exchange365_tenantId',
        'transport_exchange365_clientSecret',
    ];

    /**
     * Site setting keys (below plugin.tx_okexchange365mailer.settings.exchange365)
     * that are blinded in the "Sites YAML configuration" view.
     *
     * @var list<string>
     */
    private const BLINDED_SITE_SETTINGS = [
        'clientId',
        'tenantId',
        'clientSecret',
    ];

    private const SITE_SETTINGS_PATH = ['plugin', 'tx_okexchange365mailer', 'settings', 'exchange365'];

    public function __construct(
        private readonly SiteFinder $siteFinder,
    ) {}

    public function __invoke(ModifyBlindedConfigurationOptionsEvent $event): void
    {
        $options = $event->getBlindedConfigurationOptions();

        if ($event->getProviderIdentifier() === 'confVars') {
            $options = $this->modifyBlindedConfigurationOptions($options);
        } elseif ($event->getProviderIdentifier() === 'sitesYamlConfiguration') {
            $options = $this->modifyBlindedSiteConfigurationOptions($options);
        }

        $event->setBlindedConfigurationOptions($options);
    }

    /**
     * Blind exchange 365 credentials in ConfigurationOptions
     *
     * @param array<string, mixed> $blindedConfigurationOptions
     * @return array<string, mixed>
     */
    public function modifyBlindedConfigurationOptions(array $blindedConfigurationOptions): array
    {
        foreach (self::BLINDED_MAIL_SETTINGS as $key) {
            if (!empty($GLOBALS['TYPO3_CONF_VARS']['MAIL'][$key])) {
                $blindedConfigurationOptions['TYPO3_CONF_VARS']['MAIL'][$key]
                    = $this->blind((string)$GLOBALS['TYPO3_CONF_VARS']['MAIL'][$key]);
            }
        }

        return $blindedConfigurationOptions;
    }

    /**
     * Blind exchange 365 credentials stored as site settings (settings.yaml).
     *
     * Settings may be written as a nested tree or as dotted keys, so both shapes
     * are handled. The provider only applies blinded values for paths that exist
     * in the site configuration, so returning the shape that was found is enough.
     *
     * @param array<string, mixed> $blindedConfigurationOptions
     * @return array<string, mixed>
     */
    public function modifyBlindedSiteConfigurationOptions(array $blindedConfigurationOptions): array
    {
        foreach ($this->siteFinder->getAllSites() as $identifier => $site) {
            $settings = $site->getConfiguration()['settings'] ?? null;
            if (!is_array($settings)) {
                continue;
            }

            $dottedPrefix = implode('.', self::SITE_SETTINGS_PATH) . '.';
            $nested = $settings;
            foreach (self::SITE_SETTINGS_PATH as $segment) {
                $nested = is_array($nested) ? ($nested[$segment] ?? null) : null;
            }

            foreach (self::BLINDED_SITE_SETTINGS as $key) {
                if (is_array($nested) && !empty($nested[$key]) && is_scalar($nested[$key])) {
                    $target = &$blindedConfigurationOptions[$identifier]['settings'];
                    foreach (self::SITE_SETTINGS_PATH as $segment) {
                        $target = &$target[$segment];
                    }
                    $target[$key] = $this->blind((string)$nested[$key]);
                    unset($target);
                }
                if (!empty($settings[$dottedPrefix . $key]) && is_scalar($settings[$dottedPrefix . $key])) {
                    $blindedConfigurationOptions[$identifier]['settings'][$dottedPrefix . $key]
                        = $this->blind((string)$settings[$dottedPrefix . $key]);
                }
            }
        }

        return $blindedConfigurationOptions;
    }

    private function blind(string $value): string
    {
        return mb_substr($value, 0, 2) . '******' . mb_substr($value, -2, 2);
    }
}
