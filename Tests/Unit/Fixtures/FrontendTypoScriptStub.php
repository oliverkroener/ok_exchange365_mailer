<?php

declare(strict_types=1);

namespace OliverKroener\OkExchange365\Tests\Unit\Fixtures;

/**
 * Stand-in for TYPO3's FrontendTypoScript request attribute.
 *
 * The real TYPO3\CMS\Core\TypoScript\FrontendTypoScript is final and its constructor
 * signature changed between TYPO3 12 and 13, so it can be neither mocked nor safely
 * instantiated across the matrix. The transport only ever calls getSetupArray() on
 * whatever the attribute holds, so duck typing is sufficient and version-proof.
 */
final class FrontendTypoScriptStub
{
    /**
     * @param array<string, mixed> $setup
     */
    public function __construct(private readonly array $setup) {}

    /**
     * @return array<string, mixed>
     */
    public function getSetupArray(): array
    {
        return $this->setup;
    }
}
