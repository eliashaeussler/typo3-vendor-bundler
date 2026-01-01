<?php

declare(strict_types=1);

/*
 * This file is part of the Composer package "eliashaeussler/typo3-vendor-bundler".
 *
 * Copyright (C) 2025-2026 Elias Häußler <elias@haeussler.dev>
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

use Composer\Factory;
use Composer\IO;
use Composer\Package\Package;
use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;
use Symfony\Component\Filesystem;

use function reset;
use function sys_get_temp_dir;

/**
 * DependencySetTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Resource\DependencySet::class)]
final class DependencySetTest extends Framework\TestCase
{
    private Filesystem\Filesystem $filesystem;
    private Src\Resource\DependencySet $subject;

    public function setUp(): void
    {
        $this->filesystem = new Filesystem\Filesystem();
        $this->subject = new Src\Resource\DependencySet(
            [
                'foo/baz' => new Package('foo/baz', '1.0.0.0', '1.0.0'),
                'foo/boo' => new Package('foo/boo', '1.2.3.0', '1.2.3'),
            ],
            [
                'boo/foo' => new Package('boo/foo', '1.2.3.0', '1.2.3'),
                'baz/foo' => new Package('baz/foo', '1.0.0.0', '1.0.0'),
            ],
            [
                'another/foo' => [
                    Src\Resource\DependencyExtractionProblem::RequirementNotResolvable,
                ],
                'missing/foo' => [
                    Src\Resource\DependencyExtractionProblem::RequirementNotResolvable,
                    Src\Resource\DependencyExtractionProblem::NoMatchingVersionFound,
                ],
            ],
        );
    }

    #[Framework\Attributes\Test]
    public function requirementsReturnsRequiredPackagesPreparedForRequireSectionInComposerJson(): void
    {
        $expected = [
            'foo/baz' => '1.0.0',
            'foo/boo' => '1.2.3',
        ];

        self::assertSame($expected, $this->subject->requirements());
    }

    #[Framework\Attributes\Test]
    public function exclusionsReturnsExcludedPackagesPreparedForProvideSectionInComposerJson(): void
    {
        $expected = [
            'baz/foo' => '*',
            'boo/foo' => '*',
        ];

        self::assertSame($expected, $this->subject->exclusions());
    }

    #[Framework\Attributes\Test]
    public function problemsReturnsProblemMessages(): void
    {
        $expected = [
            'Could not resolve a dedicated Composer package for the requirement "another/foo".',
            'Could not resolve a dedicated Composer package for the requirement "missing/foo".',
            'Could not find a matching version for the Composer package "missing/foo".',
        ];

        self::assertSame($expected, $this->subject->problems());
    }

    #[Framework\Attributes\Test]
    public function dumpToFileThrowsExceptionIfGivenFileExistsAndIsNotValid(): void
    {
        $filename = $this->filesystem->tempnam(sys_get_temp_dir(), 'typo3-vendor-bundler-', '.json');

        $this->expectExceptionObject(
            new Src\Exception\DeclarationFileIsInvalid($filename),
        );

        try {
            $this->subject->dumpToFile($filename);
        } finally {
            $this->filesystem->remove($filename);
        }
    }

    #[Framework\Attributes\Test]
    public function dumpToFileCreatesAndInitializesFileIfItDoesNotExistYet(): void
    {
        $filename = $this->filesystem->tempnam(sys_get_temp_dir(), 'typo3-vendor-bundler-', '.json');

        $this->filesystem->remove($filename);

        self::assertFileDoesNotExist($filename);

        $this->subject->dumpToFile($filename);

        self::assertFileExists($filename);
        self::assertStringNotEqualsFile($filename, '');

        $this->filesystem->remove($filename);
    }

    #[Framework\Attributes\Test]
    public function dumpToFileWritesDependenciesToGivenFile(): void
    {
        $filename = $this->filesystem->tempnam(sys_get_temp_dir(), 'typo3-vendor-bundler-', '.json');

        $this->filesystem->dumpFile($filename, '{}');

        $this->subject->dumpToFile($filename);

        self::assertFileExists($filename);

        $composer = Factory::create(new IO\NullIO(), $filename);
        $requires = $composer->getPackage()->getRequires();
        $provides = $composer->getPackage()->getProvides();

        self::assertCount(2, $requires);
        self::assertArrayHasKey('foo/baz', $requires);
        self::assertSame('foo/baz', $requires['foo/baz']->getTarget());
        self::assertSame('1.0.0', $requires['foo/baz']->getPrettyConstraint());
        self::assertArrayHasKey('foo/boo', $requires);
        self::assertSame('foo/boo', $requires['foo/boo']->getTarget());
        self::assertSame('1.2.3', $requires['foo/boo']->getPrettyConstraint());

        self::assertCount(2, $provides);
        self::assertArrayHasKey('baz/foo', $provides);
        self::assertSame('baz/foo', $provides['baz/foo']->getTarget());
        self::assertSame('*', $provides['baz/foo']->getPrettyConstraint());
        self::assertArrayHasKey('boo/foo', $provides);
        self::assertSame('boo/foo', $provides['boo/foo']->getTarget());
        self::assertSame('*', $provides['boo/foo']->getPrettyConstraint());

        self::assertFalse($composer->getPackage()->getConfig()['allow-plugins'] ?? null);
        self::assertFalse($composer->getPackage()->getConfig()['lock'] ?? null);

        $this->filesystem->remove($filename);
    }

    #[Framework\Attributes\Test]
    public function dumpToFileWritesDependenciesToGivenFileAndIncludesContextFromOrigin(): void
    {
        $origin = Factory::create(new IO\NullIO(), dirname(__DIR__).'/Fixtures/Extensions/valid-composer-json/composer.json');
        $filename = $this->filesystem->tempnam(sys_get_temp_dir(), 'typo3-vendor-bundler-', '.json');

        $this->filesystem->dumpFile($filename, '{}');

        $this->subject->dumpToFile($filename, $origin);

        self::assertFileExists($filename);

        $composer = Factory::create(new IO\NullIO(), $filename);
        $repositories = $composer->getPackage()->getRepositories();
        $expectedRepository = [
            'type' => 'path',
            'url' => '../valid-no-libs',
        ];

        // Remove default (internal) packagist.org repository
        unset($repositories['packagist.org']);

        self::assertSame('foo/root-libs', $composer->getPackage()->getName());
        self::assertCount(1, $repositories);
        self::assertSame($expectedRepository, reset($repositories));

        $this->filesystem->remove($filename);
    }
}
