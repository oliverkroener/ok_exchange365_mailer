<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Exchange 365 Mail Extension for OAuth2',
    'description' => 'A TYPO3 extension for sending emails using Exchange 365 and Microsoft Graph API',
    'tags' => 'exchange365, mail, smtp, oauth2, microsoft graph',
    'category' => 'plugin',
    'author' => 'Oliver Kroener',
    'author_email' => 'ok@oliver-kroener.de',
    'state' => 'stable',
    'version' => '4.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ]
];
