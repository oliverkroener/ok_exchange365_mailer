<?php

namespace OliverKroener\OkExchange365\Hook;
class BlindedConfigurationOptionsHook
{

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
