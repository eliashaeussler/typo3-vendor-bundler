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

use Composer\Semver;
use EliasHaeussler\Typo3VendorBundler as Src;
use EliasHaeussler\Typo3VendorBundler\Tests;
use Generator;
use PHPUnit\Framework;
use Symfony\Component\Console;

/**
 * ComposerTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Resource\Composer::class)]
final class ComposerTest extends Tests\ExtensionFixtureBasedTestCase
{
    #[Framework\Attributes\Test]
    public function createAcceptsBaseDirectoryContainingComposerJsonFile(): void
    {
        $rootPath = self::getFixturePath();
        $composerJson = $rootPath.'/composer.json';

        $actual = Src\Resource\Composer::create($rootPath);

        self::assertSame($composerJson, $actual->composer->getConfig()->getConfigSource()->getName());
    }

    #[Framework\Attributes\Test]
    public function createReturnsComposerWrapperForGivenComposerJsonFile(): void
    {
        $rootPath = self::getFixturePath();
        $composerJson = $rootPath.'/composer.json';

        $actual = Src\Resource\Composer::create($composerJson);

        self::assertSame($composerJson, $actual->declarationFile());
    }

    #[Framework\Attributes\Test]
    public function createThrowsExceptionOnInvalidComposerJsonFile(): void
    {
        $rootPath = self::getFixturePath('invalid-composer-file');
        $composerJsonFile = $rootPath.'/composer.json';

        $this->expectExceptionObject(
            new Src\Exception\DeclarationFileIsInvalid($composerJsonFile),
        );

        Src\Resource\Composer::create($composerJsonFile);
    }

    #[Framework\Attributes\Test]
    public function installThrowsExceptionIfRootPathDoesNotExist(): void
    {
        $rootPath = $this->createTemporaryFixture();

        $subject = Src\Resource\Composer::create($rootPath);

        $this->filesystem->remove($rootPath);

        $this->expectExceptionObject(
            new Src\Exception\CannotInstallComposerDependencies(
                $rootPath,
                new Src\Exception\DirectoryDoesNotExist($rootPath),
            ),
        );

        $subject->install();
    }

    #[Framework\Attributes\Test]
    public function installThrowsExceptionAndWritesInstallOutputIfInstallationFails(): void
    {
        $rootPath = $this->createTemporaryFixture('invalid-dependencies');

        $subject = Src\Resource\Composer::create($rootPath);
        $output = new Console\Output\BufferedOutput();

        $this->expectExceptionObject(
            new Src\Exception\CannotInstallComposerDependencies($rootPath),
        );

        try {
            $subject->install(output: $output);
        } finally {
            self::assertStringContainsString('eliashaeussler/foo-baz', $output->fetch());
        }
    }

    /**
     * @return Generator<string, array{bool, bool}>
     */
    public static function installRespectsDevModeDataProvider(): Generator
    {
        yield 'with dev' => [true, true];
        yield 'without dev' => [false, false];
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('installRespectsDevModeDataProvider')]
    #[Framework\Attributes\WithoutErrorHandler]
    public function installRespectsDevMode(bool $includeDevDependencies, bool $expected): void
    {
        $rootPath = $this->createTemporaryFixture('valid-dev');

        $subject = Src\Resource\Composer::create($rootPath);
        $subject->install($includeDevDependencies);

        $composer = Src\Resource\Composer::create($rootPath)->composer;
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();

        self::assertNotNull(
            $localRepo->findPackage('symfony/yaml', new Semver\Constraint\MatchAllConstraint()),
        );
        self::assertSame(
            $expected,
            null !== $localRepo->findPackage('phpunit/phpunit', new Semver\Constraint\MatchAllConstraint()),
        );
    }

    /**
     * @return Generator<string, array{string, mixed}>
     */
    public static function readExtraReturnsPropertyFromExtraSectionDataProvider(): Generator
    {
        yield 'non-existing root property' => ['foo', null];
        yield 'existing root property' => [
            'typo3/cms',
            [
                'extension-key' => 'test',
            ],
        ];
        yield 'non-existing nested property' => ['foo.baz', null];
        yield 'non-existing nested property on existing root property' => ['typo3/cms.baz', null];
        yield 'existing nested property' => ['typo3/cms.extension-key', 'test'];
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('readExtraReturnsPropertyFromExtraSectionDataProvider')]
    public function readExtraReturnsPropertyFromExtraSection(string $path, mixed $expected): void
    {
        $subject = Src\Resource\Composer::create(self::getFixturePath());

        self::assertSame($expected, $subject->readExtra($path));
    }

    /**
     * @return Generator<string, array{string, string, array<string, mixed>}>
     */
    public static function writeExtraAddsGivenExtraPropertyToComposerJsonDataProvider(): Generator
    {
        yield 'existing root property' => [
            'typo3/cms',
            'foo',
            [
                'typo3/cms' => 'foo',
            ],
        ];
        yield 'existing nested property' => [
            'typo3/cms.extension-key',
            'foo',
            [
                'typo3/cms' => [
                    'extension-key' => 'foo',
                ],
            ],
        ];
        yield 'non-existing nested property on existing root property' => [
            'typo3/cms.foo.baz',
            'boo',
            [
                'typo3/cms' => [
                    'extension-key' => 'test',
                    'foo' => [
                        'baz' => 'boo',
                    ],
                ],
            ],
        ];
        yield 'non-existing root property' => [
            'foo',
            'baz',
            [
                'typo3/cms' => [
                    'extension-key' => 'test',
                ],
                'foo' => 'baz',
            ],
        ];
        yield 'non-existing nested property' => [
            'foo.baz',
            'boo',
            [
                'typo3/cms' => [
                    'extension-key' => 'test',
                ],
                'foo' => [
                    'baz' => 'boo',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('writeExtraAddsGivenExtraPropertyToComposerJsonDataProvider')]
    public function writeExtraAddsGivenExtraPropertyToComposerJson(string $path, string $value, array $expected): void
    {
        $rootPath = $this->createTemporaryFixture();
        $subject = Src\Resource\Composer::create($rootPath);

        $subject->writeExtra($path, $value);

        self::assertSame($expected, $subject->composer->getPackage()->getExtra());
    }

    #[Framework\Attributes\Test]
    public function declarationFileReturnsPathToComposerJson(): void
    {
        $rootPath = self::getFixturePath();
        $composerJson = $rootPath.'/composer.json';
        $subject = Src\Resource\Composer::create($rootPath);

        self::assertSame($composerJson, $subject->declarationFile());
    }

    #[Framework\Attributes\Test]
    public function rootPathReturnsDirectoryNameOfComposerJson(): void
    {
        $rootPath = self::getFixturePath();
        $subject = Src\Resource\Composer::create($rootPath);

        self::assertSame($rootPath, $subject->rootPath());
    }
}
