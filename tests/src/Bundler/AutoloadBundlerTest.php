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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Bundler;

use Composer\Factory;
use Composer\IO;
use Composer\Package;
use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;

use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;

/**
 * AutoloadBundlerTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Bundler\AutoloadBundler::class)]
final class AutoloadBundlerTest extends Framework\TestCase
{
    private Filesystem\Filesystem $filesystem;
    private Console\Output\BufferedOutput $output;
    private Src\Bundler\AutoloadBundler $subject;

    public function setUp(): void
    {
        $this->filesystem = new Filesystem\Filesystem();
        $this->output = new Console\Output\BufferedOutput(Console\Output\OutputInterface::VERBOSITY_VERBOSE);
        $this->subject = $this->createSubject('valid');
    }

    #[Framework\Attributes\Test]
    public function constructorThrowsExceptionIfComposerFileIsInvalid(): void
    {
        $rootPath = $this->getFixturePath('invalid-composer-file');

        $this->expectExceptionObject(
            new Src\Exception\DeclarationFileIsInvalid($rootPath.'/composer.json'),
        );

        $this->createSubject('invalid-composer-file');
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleExtractsVendorLibrariesFromRootPackageIfComposerJsonForVendorLibrariesIsMissing(): void
    {
        $librariesPath = $this->getFixturePath('valid-no-libs').'/libs';
        $subject = $this->createSubject('valid-no-libs');

        $this->filesystem->remove($librariesPath);

        $subject->bundle(new Src\Config\AutoloadTarget('composer_modified.json', true));

        $output = $this->output->fetch();

        self::assertStringContainsString('🔎 Extracting dependencies from root package... Done', $output);
        self::assertStringContainsString('✍️ Creating temporary composer.json file for extracted vendor libraries... Done', $output);

        $actual = $this->parseComposerJson($librariesPath.'/composer.json');
        $requires = $actual->getRequires();

        self::assertCount(1, $requires);
        self::assertArrayHasKey('eliashaeussler/sse', $requires);
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfProblemsOccurDuringDependencyExtraction(): void
    {
        $librariesPath = $this->getFixturePath('invalid-libs').'/libs';
        $subject = $this->createSubject('invalid-libs');

        $this->filesystem->remove($librariesPath);

        $this->expectExceptionObject(
            new Src\Exception\DependencyExtractionFailed([
                'Could not resolve a dedicated Composer package for the requirement "eliashaeussler/sssseee".',
            ]),
        );

        try {
            $subject->bundle();
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🔎 Extracting dependencies from root package... Failed', $output);
            self::assertStringContainsString('Could not resolve a dedicated Composer package for the requirement "eliashaeussler/sssseee".', $output);
        }
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfPathToVendorLibrariesDoesNotExist(): void
    {
        $librariesPath = $this->getFixturePath('valid').'/foo';
        $subject = $this->createSubject('valid', 'foo');

        $this->expectExceptionObject(
            new Src\Exception\DirectoryDoesNotExist($librariesPath),
        );

        $subject->bundle(extractDependencies: false);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleExcludesFilesFromLibsClassMapInComposerJsonFile(): void
    {
        $targetFile = $this->getFixturePath('valid').'/composer_modified.json';

        try {
            $this->subject->bundle(
                new Src\Config\AutoloadTarget('composer_modified.json', true),
                false,
                true,
                false,
                [
                    'vendor/composer/InstalledVersions.php',
                ],
            );
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🌱 Loading class map from root package... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString(
                '⛔ Removing "vendor/composer/InstalledVersions.php" from class map... Done',
                $output,
            );
            self::assertFileExists($targetFile);

            $actual = $this->parseComposerJson($targetFile);

            self::assertIsArray($actual->getAutoload()['classmap'] ?? null);
            self::assertNotContains('libs/vendor/composer/InstalledVersions.php', $actual->getAutoload()['classmap']);
        }
    }

    #[Framework\Attributes\Test]
    public function bundleShowsErrorIfFileToExcludeFromClassMapIsNotIncludedInClassMap(): void
    {
        try {
            $this->subject->bundle(
                new Src\Config\AutoloadTarget('composer_modified.json', true),
                false,
                true,
                false,
                [
                    'foo.php',
                ],
            );
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('⛔ Removing "foo.php" from class map... Failed', $output);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleThrowsExceptionIfRootComposerJsonContainsMultiplePathsForASingleNamespace(): void
    {
        $composerJson = $this->getFixturePath('invalid-multiple-namespace-paths').'/composer.json';
        $subject = $this->createSubject('invalid-multiple-namespace-paths');

        $this->expectExceptionObject(
            new Src\Exception\DeclarationFileIsInvalid($composerJson, '[autoload][psr-4][Foo\\]'),
        );

        try {
            $subject->bundle(new Src\Config\AutoloadTarget());
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🌱 Loading PSR-4 namespaces from root package... Failed', $output);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleFlattensSingleValueArrayOfNamespacePathsInComposerJsonFile(): void
    {
        $targetFile = $this->getFixturePath('valid-single-array-namespace-path').'/composer_modified.json';
        $subject = $this->createSubject('valid-single-array-namespace-path');

        try {
            $subject->bundle(
                new Src\Config\AutoloadTarget('composer_modified.json', true),
            );
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('🌱 Loading PSR-4 namespaces from root package... Done', $output);
            self::assertStringContainsString('🎊 Dumping merged autoload configuration... Done', $output);

            $actual = $this->parseComposerJson($targetFile);

            self::assertIsArray($actual->getAutoload()['psr-4'] ?? null);
            self::assertCount(1, $actual->getAutoload()['psr-4']);
        }
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfTargetComposerJsonFileAlreadyExists(): void
    {
        $targetFile = $this->getFixturePath('valid').'/composer.json';

        $this->expectExceptionObject(
            new Src\Exception\FileAlreadyExists($targetFile),
        );

        try {
            $this->subject->bundle(new Src\Config\AutoloadTarget());
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🌱 Loading class map from root package... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('🌱 Loading PSR-4 namespaces from root package... Done', $output);
        }
    }

    #[Framework\Attributes\Test]
    public function bundleBacksUpSourceFiles(): void
    {
        $fixturePath = $this->getFixturePath('valid');
        $composerJson = $fixturePath.'/composer.json';
        $composerJsonBackup = $fixturePath.'/composer.json.bak';

        $this->filesystem->remove($composerJsonBackup);

        self::assertFileExists($composerJson);
        self::assertFileDoesNotExist($composerJsonBackup);

        $composerJsonSource = file_get_contents($composerJson);

        try {
            $this->subject->bundle(
                new Src\Config\AutoloadTarget(overwrite: true),
                false,
                true,
                true,
            );
        } finally {
            // Restore original source files
            file_put_contents($composerJson, $composerJsonSource);

            self::assertFileExists($composerJsonBackup);
            self::assertIsString($composerJsonSource);
            self::assertStringEqualsFile($composerJsonBackup, $composerJsonSource);

            $output = $this->output->fetch();

            self::assertStringContainsString('🌱 Loading class map from root package... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('🌱 Loading PSR-4 namespaces from root package... Done', $output);
            self::assertStringContainsString('🦖 Backing up source files... Done', $output);
        }
    }

    #[Framework\Attributes\Test]
    public function bundleDumpsMergedAutoloadConfiguration(): void
    {
        $targetFile = $this->getFixturePath('valid').'/composer_modified.json';

        try {
            $this->subject->bundle(
                new Src\Config\AutoloadTarget('composer_modified.json', true),
            );
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🌱 Loading class map from root package... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('🌱 Loading PSR-4 namespaces from root package... Done', $output);
            self::assertStringContainsString('🎊 Dumping merged autoload configuration... Done', $output);

            $actual = $this->parseComposerJson($targetFile);

            self::assertIsArray($actual->getAutoload()['classmap'] ?? null);
            self::assertGreaterThan(2, count($actual->getAutoload()['classmap']));
            self::assertIsArray($actual->getAutoload()['psr-4'] ?? null);
            self::assertCount(1, $actual->getAutoload()['psr-4']);
        }
    }

    private function parseComposerJson(string $filename): Package\RootPackageInterface
    {
        self::assertFileExists($filename);

        return Factory::create(new IO\NullIO(), $filename)->getPackage();
    }

    private function createSubject(string $extension, string $librariesPath = 'libs'): Src\Bundler\AutoloadBundler
    {
        return new Src\Bundler\AutoloadBundler(
            $this->getFixturePath($extension),
            $librariesPath,
            $this->output,
        );
    }

    private function getFixturePath(string $extension): string
    {
        return dirname(__DIR__).'/Fixtures/Extensions/'.$extension;
    }
}
