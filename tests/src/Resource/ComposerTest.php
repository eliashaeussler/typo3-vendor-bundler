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
use Generator;
use PHPUnit\Framework;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;

/**
 * ComposerTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Resource\Composer::class)]
final class ComposerTest extends Framework\TestCase
{
    private Filesystem\Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem\Filesystem();
    }

    #[Framework\Attributes\Test]
    public function createAcceptsBaseDirectoryContainingComposerJsonFile(): void
    {
        $composerJson = dirname(__DIR__).'/Fixtures/Extensions/valid';

        $actual = Src\Resource\Composer::create($composerJson);

        self::assertSame(
            $composerJson.'/composer.json',
            $actual->composer->getConfig()->getConfigSource()->getName(),
        );
    }

    #[Framework\Attributes\Test]
    public function createReturnsComposerWrapperForGivenComposerJsonFile(): void
    {
        $composerJson = dirname(__DIR__).'/Fixtures/Extensions/valid/composer.json';

        $actual = Src\Resource\Composer::create($composerJson);

        self::assertSame($composerJson, $actual->composer->getConfig()->getConfigSource()->getName());
    }

    #[Framework\Attributes\Test]
    public function createThrowsExceptionOnInvalidComposerJsonFile(): void
    {
        $composerJsonFile = dirname(__DIR__).'/Fixtures/Extensions/invalid-composer-file/composer.json';

        $this->expectExceptionObject(
            new Src\Exception\DeclarationFileIsInvalid($composerJsonFile),
        );

        Src\Resource\Composer::create($composerJsonFile);
    }

    #[Framework\Attributes\Test]
    public function installThrowsExceptionIfRootPathDoesNotExist(): void
    {
        $sourcePath = dirname(__DIR__).'/Fixtures/Extensions/valid';
        $rootPath = dirname(__DIR__).'/Fixtures/Extensions/valid-temporary';

        $this->filesystem->remove($rootPath);
        $this->filesystem->mirror($sourcePath, $rootPath);

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
        $sourcePath = dirname(__DIR__).'/Fixtures/Extensions/invalid-dependencies';
        $rootPath = dirname(__DIR__).'/Fixtures/Extensions/valid-temporary';

        $this->filesystem->remove($rootPath);
        $this->filesystem->mirror($sourcePath, $rootPath);

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
        $sourcePath = dirname(__DIR__).'/Fixtures/Extensions/valid-dev';
        $rootPath = dirname(__DIR__).'/Fixtures/Extensions/valid-temporary';

        $this->filesystem->remove($rootPath);
        $this->filesystem->mirror($sourcePath, $rootPath);

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
}
