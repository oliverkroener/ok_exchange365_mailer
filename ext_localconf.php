<?php

defined('TYPO3_MODE') || die();

// Blind the Exchange 365 credentials in System > Configuration
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][\TYPO3\CMS\Lowlevel\Controller\ConfigurationController::class]['modifyBlindedConfigurationOptions'][]
    = \OliverKroener\OkExchange365\Hook\BlindedConfigurationOptionsHook::class;
