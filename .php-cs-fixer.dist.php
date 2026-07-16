<?php

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()
    ->in(__DIR__ . '/Classes')
    ->append([
        __DIR__ . '/ext_emconf.php',
        __DIR__ . '/ext_localconf.php',
    ]);
return $config;
