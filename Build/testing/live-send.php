<?php

/*
 * Sends one real message through the configured Exchange365 transport in CLI
 * context, where the transport reads its credentials from TYPO3_CONF_VARS.
 *
 * Executed inside a lab by Build/Scripts/runTests.sh --live. A plain script rather
 * than a TYPO3 command, so it runs unchanged against every branch from TYPO3 9 on.
 * Not part of the shipped extension runtime.
 */

$autoload = '/var/www/html/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "live-send: no autoloader at {$autoload}\n");
    exit(1);
}

// TYPO3 9 cannot work out its paths from a script outside the project, so tell
// it where the project lives. Newer versions find it themselves.
if (getenv('TYPO3_PATH_ROOT') === false) {
    putenv('TYPO3_PATH_ROOT=/var/www/html/public');
}
if (getenv('TYPO3_PATH_APP') === false) {
    putenv('TYPO3_PATH_APP=/var/www/html');
}

$classLoader = require $autoload;
require_once __DIR__ . '/Fixture/MatrixMail.php';

// TYPO3 9 derives the site root from the path of the running script (argv[0]),
// which here is the read-only mount. Present the script as the core CLI binary,
// exactly like typo3/sysext/core/bin/typo3 does. Nothing may touch TYPO3 classes
// before run(), so the major is read from the core's ext_emconf.php.
$entryPointLevel = 0;
$coreEmconf = '/var/www/html/public/typo3/sysext/core/ext_emconf.php';
if (is_file($coreEmconf) && preg_match("/'version' => '(\\d+)\\./", (string)file_get_contents($coreEmconf), $match) && (int)$match[1] < 10) {
    $_SERVER['argv'][0] = '/var/www/html/public/typo3/sysext/core/bin/typo3';
    $entryPointLevel = 4;
}

\TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run(
    $entryPointLevel,
    \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_CLI
);
\TYPO3\CMS\Core\Core\Bootstrap::init($classLoader);

$transport = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] ?? '';
if (strpos((string)$transport, 'Exchange365Transport') === false) {
    fwrite(STDERR, "live-send: MAIL.transport is '{$transport}', not the Exchange365 transport\n");
    exit(1);
}

try {
    $subject = \OliverKroener\OkExchange365\TestFixture\MatrixMail::send('cli');
} catch (\Throwable $e) {
    fwrite(STDERR, 'live-send: FAILED: ' . get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "live-send: accepted by Microsoft Graph: {$subject}\n");
exit(0);
