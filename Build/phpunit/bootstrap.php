<?php

declare(strict_types=1);

/*
 * Locates the autoloader and the typo3/testing-framework bootstrap, whichever way
 * the package is mounted.
 *
 * PHPUnit resolves `bootstrap=` relative to the XML file, but vendor/ lives in a
 * different place depending on how the suite is run:
 *
 *   standalone in the extension        <ext>/vendor
 *   inside a matrix lab                /var/www/html/vendor   (ext at /var/www/ext-src)
 *   inside a project's packages/ dir   <project>/vendor
 *
 * Walking a candidate list keeps ONE committed XML per suite working in all three,
 * on every branch.
 */

$suite = getenv('OK_EXCHANGE365_TEST_SUITE') ?: 'Unit';

$candidates = [
    dirname(__DIR__, 2) . '/vendor',
    '/var/www/html/vendor',
    dirname(__DIR__, 4) . '/vendor',
    dirname(__DIR__, 5) . '/vendor',
];

$vendor = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate . '/autoload.php')) {
        $vendor = $candidate;
        break;
    }
}

if ($vendor === null) {
    fwrite(STDERR, "Could not locate vendor/autoload.php. Tried:\n  " . implode("\n  ", $candidates) . "\n");
    exit(1);
}

require $vendor . '/autoload.php';

$frameworkBootstrap = sprintf(
    '%s/typo3/testing-framework/Resources/Core/Build/%sTestsBootstrap.php',
    $vendor,
    $suite
);

if (!is_file($frameworkBootstrap)) {
    fwrite(STDERR, "typo3/testing-framework is not installed ({$frameworkBootstrap} missing).\n");
    exit(1);
}

require $frameworkBootstrap;
