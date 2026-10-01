<?php

/*
 * Lab-only: point the root page at the matrix TypoScript (frontend.typoscript).
 *
 * Executed inside a lab by Build/Scripts/runTests.sh. Uses plain mysqli against the
 * DDEV database so it behaves the same on TYPO3 9 through 14. Every other
 * sys_template on the root page is soft-deleted; on TYPO3 13+ a root sys_template
 * also takes precedence over the site's own setup.typoscript.
 */

$typoScript = file_get_contents(__DIR__ . '/frontend.typoscript');
$constants = file_get_contents(__DIR__ . '/frontend.constants.typoscript');
$db = new mysqli('db', 'db', 'db', 'db', 3306);

$rootPage = (int)$db->query('SELECT uid FROM pages WHERE pid = 0 AND deleted = 0 ORDER BY uid LIMIT 1')->fetch_row()[0];
if ($rootPage < 1) {
    fwrite(STDERR, "install-fixture: no root page\n");
    exit(1);
}
$db->query('UPDATE pages SET is_siteroot = 1 WHERE uid = ' . $rootPage);
$db->query('UPDATE sys_template SET deleted = 1 WHERE pid = ' . $rootPage);

$statement = $db->prepare(
    'INSERT INTO sys_template (pid, title, root, clear, include_static_file, constants, config, sorting, tstamp, crdate)'
    . ' VALUES (?, ?, 1, 3, ?, ?, ?, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())'
);
$title = 'ex365-matrix';
$static = 'EXT:ok_exchange365_mailer/Configuration/TypoScript/';
$statement->bind_param('issss', $rootPage, $title, $static, $constants, $typoScript);
$statement->execute();

echo "install-fixture: sys_template on page {$rootPage}\n";
