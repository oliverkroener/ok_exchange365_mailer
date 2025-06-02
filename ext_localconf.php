<?php

defined('TYPO3') || die();

if ((\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class))->getMajorVersion() < 12 ) {
    // Add Hook for blindedConfigurationValues
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][\TYPO3\CMS\Lowlevel\Controller\ConfigurationController::class]['modifyBlindedConfigurationOptions'][] =
        OliverKroener\OkExchange365\Hook\BlindedConfigurationOptionsHook::class;
}
