<?php
defined('TYPO3') || die();

call_user_func(
    function () {
        // Register the custom mailer as the default mailer
        // $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailer'] = \OliverKroener\OkExchange365\Mail\Exchange365Mailer::class;
    }
);
