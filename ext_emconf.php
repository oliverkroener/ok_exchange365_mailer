<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Exchange 365 Mail Extension for OAuth2',
    'description' => 'A TYPO3 extension for sending emails using Exchange 365 and Microsoft Graph API',
    'category' => 'plugin',
    'author' => 'Oliver Kroener',
    'author_email' => 'ok@oliver-kroener.de',
    'state' => 'beta',
    'version' => '0.9.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.0.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
