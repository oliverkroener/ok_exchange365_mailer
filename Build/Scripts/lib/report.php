<?php

declare(strict_types=1);

/**
 * Renders report.md and results.json from the tab-separated rows runTests.sh collected.
 *
 * Row format: major \t layer \t status \t detail
 */

$rowsFile = $argv[1] ?? '';
$matrixFile = getenv('MATRIX_FILE') ?: '';
$runId = getenv('RUN_ID') ?: 'unknown';
$reportMd = getenv('REPORT_MD') ?: 'report.md';
$reportJson = getenv('REPORT_JSON') ?: 'results.json';

$matrix = json_decode((string)file_get_contents($matrixFile), true) ?: [];
$versions = $matrix['versions'] ?? [];

$rows = [];
foreach (explode("\n", (string)file_get_contents($rowsFile)) as $line) {
    if (trim($line) === '') {
        continue;
    }
    $parts = explode("\t", $line);
    $rows[] = [
        'major' => $parts[0] ?? '',
        'layer' => $parts[1] ?? '',
        'status' => $parts[2] ?? '',
        'detail' => $parts[3] ?? '',
    ];
}

// Column order follows the matrix file; row order follows first appearance.
$majors = [];
foreach ($versions as $v) {
    $majors[] = (string)$v['major'];
}
$majors = array_values(array_filter($majors, static function (string $m) use ($rows): bool {
    foreach ($rows as $r) {
        if ($r['major'] === $m) {
            return true;
        }
    }
    return false;
}));

$layers = [];
foreach ($rows as $r) {
    if (!in_array($r['layer'], $layers, true)) {
        $layers[] = $r['layer'];
    }
}

$cell = static function (string $major, string $layer) use ($rows): ?array {
    foreach ($rows as $r) {
        if ($r['major'] === $major && $r['layer'] === $layer) {
            return $r;
        }
    }
    return null;
};

$mark = static function (?array $r): string {
    if ($r === null) {
        return '–';
    }
    return match ($r['status']) {
        'pass' => 'OK',
        'fail' => 'FAIL',
        'skip' => 'SKIP',
        default => $r['status'],
    };
};

// ------------------------------------------------------------------------- markdown

$out = [];
$out[] = '# Test matrix report';
$out[] = '';
$out[] = sprintf('*Run `%s` — %s UTC*', $runId, gmdate('Y-m-d H:i:s'));
$out[] = '';

$failed = 0;
foreach ($rows as $r) {
    if ($r['status'] === 'fail') {
        $failed++;
    }
}
$out[] = $failed === 0
    ? '**Result: green.** Every executed check passed.'
    : sprintf('**Result: %d failed check%s.** Labs were kept for inspection.', $failed, $failed === 1 ? '' : 's');
$out[] = '';

// environment
$out[] = '## Environment';
$out[] = '';
$out[] = '| TYPO3 | Branch tested | PHP | Database | Installer |';
$out[] = '|---|---|---|---|---|';
foreach ($versions as $v) {
    if (!in_array((string)$v['major'], $majors, true)) {
        continue;
    }
    $out[] = sprintf(
        '| %s | `%s` | %s | %s | %s |',
        $v['major'],
        $v['branch'],
        $v['php'],
        $v['db'],
        $v['installer'] === 'setup' ? '`typo3 setup`' : 'TYPO3 Console'
    );
}
$out[] = '';
$out[] = '> TYPO3 11 and 12 are ELTS. A public Composer install gets the last freely';
$out[] = '> published patch, which may carry known advisories. That is fine for a';
$out[] = '> throwaway lab, but a lab is not a security-current installation.';
$out[] = '';

// the matrix
$out[] = '## Matrix';
$out[] = '';
$header = '| Check |';
$sep = '|---|';
foreach ($majors as $m) {
    $header .= sprintf(' v%s |', $m);
    $sep .= '---|';
}
$out[] = $header;
$out[] = $sep;
foreach ($layers as $layer) {
    $line = sprintf('| %s |', $layer);
    foreach ($majors as $m) {
        $line .= sprintf(' %s |', $mark($cell($m, $layer)));
    }
    $out[] = $line;
}
$out[] = '';
$out[] = 'OK = passed · FAIL = failed · SKIP = not run, reason below · – = not reached';
$out[] = '';

// findings
$findings = array_values(array_filter($rows, static fn (array $r): bool => $r['status'] === 'fail'));
if ($findings !== []) {
    $out[] = '## Findings';
    $out[] = '';
    foreach ($findings as $f) {
        $out[] = sprintf('### TYPO3 %s — %s', $f['major'], $f['layer']);
        $out[] = '';
        $out[] = $f['detail'] !== '' ? $f['detail'] : 'No detail captured.';
        $out[] = '';
        $out[] = sprintf(
            '**Reproduce:** `Build/Scripts/runTests.sh --versions=%s --layers=%s --keep-labs`',
            $f['major'],
            $f['layer']
        );
        $out[] = '';
    }
}

// skips always carry a reason
$skips = array_values(array_filter($rows, static fn (array $r): bool => $r['status'] === 'skip'));
if ($skips !== []) {
    $out[] = '## Skipped';
    $out[] = '';
    foreach ($skips as $s) {
        $out[] = sprintf('- **v%s %s** — %s', $s['major'], $s['layer'], $s['detail'] ?: 'no reason recorded');
    }
    $out[] = '';
}

file_put_contents($reportMd, implode("\n", $out) . "\n");

// ----------------------------------------------------------------------------- json

file_put_contents($reportJson, json_encode([
    'runId' => $runId,
    'generated' => gmdate('c'),
    'failed' => $failed,
    'majors' => $majors,
    'layers' => $layers,
    'results' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

printf("Report written to %s\n", $reportMd);
