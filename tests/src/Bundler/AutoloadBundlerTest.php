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

        self::assertStringContainsString('🔎 Extracting vendor libraries from root package... Done', $output);
        self::assertStringContainsString('✍️ Creating composer.json file for extracted vendor libraries... Done', $output);

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

            self::assertStringContainsString('🔎 Extracting vendor libraries from root package... Failed', $output);
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
                    'baz.php',
                ],
            );
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('📦 Installing vendor libraries... Done', $output);
            self::assertStringContainsString('🪄 Parsing autoloads... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('♨️ Merging PSR-4 namespaces... Done', $output);
            self::assertStringContainsString('⛔ Removed baz.php from class map', $output);
            self::assertStringContainsString('🎊 Dumping merged autoload configuration... Done', $output);
            self::assertFileExists($targetFile);

            $actual = $this->parseComposerJson($targetFile);

            self::assertIsArray($actual->getAutoload()['classmap'] ?? null);
            self::assertNotContains('libs/baz.php', $actual->getAutoload()['classmap']);
        }
    }

    #[Framework\Attributes\Test]
    public function bundleShowsWarningIfFileToExcludeFromClassMapIsNotIncludedInClassMap(): void
    {
        $this->output->setVerbosity(Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE);

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

            self::assertStringContainsString('⚠️ File foo.php not found in class map', $output);
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

            self::assertStringContainsString('📦 Installing vendor libraries... Done', $output);
            self::assertStringContainsString('🪄 Parsing autoloads... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('♨️ Merging PSR-4 namespaces... Done', $output);
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

            self::assertStringContainsString('📦 Installing vendor libraries... Done', $output);
            self::assertStringContainsString('🪄 Parsing autoloads... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('♨️ Merging PSR-4 namespaces... Done', $output);
            self::assertStringContainsString('🦖 Backing up source files... Done', $output);
            self::assertStringContainsString('🎊 Dumping merged autoload configuration... Done', $output);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleDumpsMergedAutoloadConfiguration(): void
    {
        $targetFile = $this->getFixturePath('valid').'/composer_modified.json';

        try {
            $this->subject->bundle(
                new Src\Config\AutoloadTarget('composer_modified.json', true),
            );
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('📦 Installing vendor libraries... Done', $output);
            self::assertStringContainsString('🪄 Parsing autoloads... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('♨️ Merging PSR-4 namespaces... Done', $output);
            self::assertStringContainsString('🎊 Dumping merged autoload configuration... Done', $output);

            $actual = $this->parseComposerJson($targetFile);

            self::assertIsArray($actual->getAutoload()['classmap'] ?? null);
            self::assertCount(2, $actual->getAutoload()['classmap']);
            self::assertIsArray($actual->getAutoload()['psr-4'] ?? null);
            self::assertGreaterThan(1, count($actual->getAutoload()['psr-4']));
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
