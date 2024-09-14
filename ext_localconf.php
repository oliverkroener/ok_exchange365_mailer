<?php

defined('TYPO3') || die();

use TYPO3\CMS\Core\Utility\GeneralUtility;
use OliverKroener\OkExchange365\Mail\Exchange365Mailer;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\DependencyInjection\Container;

// Register your custom mailer as the default mailer service
call_user_func(function () {
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailer'] = Exchange365Mailer::class;

    // Override the default mailer service with your custom implementation
    //$container = GeneralUtility::makeInstance(Container::class);
    //$container->registerImplementation(MailerInterface::class, CustomMailer::class);
});