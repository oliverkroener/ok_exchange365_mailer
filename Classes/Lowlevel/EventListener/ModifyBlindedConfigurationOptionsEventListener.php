<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Lowlevel\EventListener;

use TYPO3\CMS\Lowlevel\Event\ModifyBlindedConfigurationOptionsEvent;

final class ModifyBlindedConfigurationOptionsEventListener
{
    public function __invoke(ModifyBlindedConfigurationOptionsEvent $event): void
    {
        $options = $event->getBlindedConfigurationOptions();

        if ($event->getProviderIdentifier() === 'confVars') {
            $options = $this->modifyBlindedConfigurationOptions($options);
        }

        $event->setBlindedConfigurationOptions($options);
    }

    private static $blindedMailSettings = [
        'transport_exchange365_clientId',
        'transport_exchange365_tenantId',
        'transport_exchange365_clientSecret',
    ];

    /**
     * Blind exchange 365 credentials in ConfigurationOptions
     *
     * @param array $blindedConfigurationOptions
     * @return array
     */
    public function modifyBlindedConfigurationOptions(array $blindedConfigurationOptions): array
    {
        foreach (self::$blindedMailSettings as $key) {
            if (!empty($GLOBALS['TYPO3_CONF_VARS']['MAIL'][$key])) {
                $blindedConfigurationOptions['TYPO3_CONF_VARS']['MAIL'][$key] =
                    mb_substr($GLOBALS['TYPO3_CONF_VARS']['MAIL'][$key], 0, 2) .
                    '******' .
                    mb_substr($GLOBALS['TYPO3_CONF_VARS']['MAIL'][$key], -2, 2);
            }
        }

        return $blindedConfigurationOptions;
    }

}
