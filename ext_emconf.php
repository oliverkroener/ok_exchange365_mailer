<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Exchange 365 Mail Extension for OAuth2',
    'description' => 'A TYPO3 extension for sending emails using Exchange 365 and Microsoft Graph API',
    'category' => 'plugin',
    'author' => 'Oliver Kroener',
    'author_email' => 'ok@oliver-kroener.de',
    'state' => 'stable',
    'version' => '2.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ]
];
