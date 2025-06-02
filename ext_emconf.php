<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Exchange 365 Mail Extension for OAuth2',
    'description' => 'A TYPO3 extension for sending emails using Exchange 365 and Microsoft Graph API',
    'category' => 'plugin',
    'author' => 'Oliver Kroener',
    'author_email' => 'ok@oliver-kroener.de',
    'state' => 'stable',
    'version' => '3.0.3',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
            'ok_typo3_helper' => '2.0.0-2.0.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ]
];
