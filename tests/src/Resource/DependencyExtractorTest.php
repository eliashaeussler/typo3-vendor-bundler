<?php

declare(strict_types=1);

/*
 * This file is part of the Composer package "eliashaeussler/typo3-vendor-bundler".
 *
 * Copyright (C) 2025 Elias Häußler <elias@haeussler.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace EliasHaeussler\Typo3VendorBundler\Tests\Resource;

use Composer\Composer;
use Composer\Factory;
use Composer\IO;
use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;

use function dirname;

/**
 * DependencyExtractorTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Resource\DependencyExtractor::class)]
final class DependencyExtractorTest extends Framework\TestCase
{
    private Src\Resource\DependencyExtractor $subject;

    public function setUp(): void
    {
        $this->subject = new Src\Resource\DependencyExtractor();
    }

    #[Framework\Attributes\Test]
    public function extractExcludesPlatformPackagesAndPackagesProvidedByFrameworkPackagesFromDependencySet(): void
    {
        $actual = $this->subject->extract($this->createComposerInstance());

        self::assertCount(1, $actual->requiredPackages);
        self::assertArrayHasKey('eliashaeussler/sse', $actual->requiredPackages);
        self::assertArrayNotHasKey('php', $actual->requiredPackages);
        self::assertArrayNotHasKey('symfony/console', $actual->requiredPackages);
    }

    #[Framework\Attributes\Test]
    public function extractLogsExtractionProblemIfRequiredPackageIsNotResolvable(): void
    {
        $expected = [
            'eliashaeussler/sssseee' => [
                Src\Resource\DependencyExtractionProblem::RequirementNotResolvable,
            ],
        ];

        $actual = $this->subject->extract($this->createComposerInstance('invalid-libs'));

        self::assertSame([], $actual->requiredPackages);
        self::assertSame($expected, $actual->extractionProblems);
    }

    #[Framework\Attributes\Test]
    public function extractLogsExtractionProblemIfMatchingVersionForRequiredPackageCannotBeFound(): void
    {
        $expected = [
            'eliashaeussler/cache-warmup' => [
                Src\Resource\DependencyExtractionProblem::NoMatchingVersionFound,
            ],
        ];

        $actual = $this->subject->extract($this->createComposerInstance('invalid-libs-constraint'));

        self::assertArrayHasKey('eliashaeussler/sse', $actual->requiredPackages);
        self::assertArrayNotHasKey('eliashaeussler/cache-warmup', $actual->requiredPackages);
        self::assertSame($expected, $actual->extractionProblems);
    }

    #[Framework\Attributes\Test]
    public function extractExcludesTransitiveDependenciesAlreadyProvidedByFrameworkPackages(): void
    {
        $actual = $this->subject->extract($this->createComposerInstance());

        // psr/http-message is required by eliashaeussler/sse
        self::assertArrayHasKey('psr/http-message', $actual->excludedPackages);
    }

    private function createComposerInstance(string $fixture = 'valid-no-libs'): Composer
    {
        return Factory::create(
            new IO\NullIO(),
            dirname(__DIR__).'/Fixtures/Extensions/'.$fixture.'/composer.json',
        );
    }
}
