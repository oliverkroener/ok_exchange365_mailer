<?php

declare(strict_types=1);

/*
 * Coding standards configuration used by the cross-version test matrix.
 *
 * The repository relies on the parent project's root .php-cs-fixer.dist.php for
 * day-to-day work; this one exists so the matrix can check the package standalone,
 * from whatever path it is mounted at inside a lab.
 *
 * NOTE: the TYPO3 CGL preset (@PER-CS + @DoctrineAnnotation) does NOT enforce
 * declare(strict_types=1). A file missing it passes this fixer silently - check new
 * classes by eye.
 */

$config = \TYPO3\CodingStandards\CsFixerConfig::create();

$config->getFinder()
    ->in(dirname(__DIR__))
    ->exclude([
        'Build',
        'Documentation',
        'Documentation-GENERATED-temp',
        'vendor',
        'var',
    ]);

return $config;
