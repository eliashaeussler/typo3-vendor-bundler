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

use EliasHaeussler\Typo3VendorBundler as Src;
use EliasHaeussler\Typo3VendorBundler\Tests;
use PHPUnit\Framework;
use Symfony\Component\Console;

use function count;
use function file_get_contents;
use function file_put_contents;

/**
 * AutoloadBundlerTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Bundler\AutoloadBundler::class)]
final class AutoloadBundlerTest extends Tests\ExtensionFixtureBasedTestCase
{
    private Console\Output\BufferedOutput $output;
    private string $rootPath;
    private Src\Bundler\AutoloadBundler $subject;

    public function setUp(): void
    {
        parent::setUp();

        $this->output = new Console\Output\BufferedOutput(Console\Output\OutputInterface::VERBOSITY_VERBOSE);
        $this->rootPath = $this->createTemporaryFixture();
        $this->subject = $this->createSubject($this->rootPath);
    }

    #[Framework\Attributes\Test]
    public function constructorThrowsExceptionIfComposerFileIsInvalid(): void
    {
        $rootPath = $this->createTemporaryFixture('invalid-composer-file');

        $this->expectExceptionObject(
            new Src\Exception\DeclarationFileIsInvalid($rootPath.'/composer.json'),
        );

        $this->createSubject($rootPath);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleExtractsVendorLibrariesFromRootPackageIfComposerJsonForVendorLibrariesIsMissing(): void
    {
        $rootPath = $this->createTemporaryFixture('valid-no-libs');
        $librariesPath = $rootPath.'/libs';
        $subject = $this->createSubject($rootPath);

        $this->filesystem->remove($librariesPath);

        $subject->bundle();

        $output = $this->output->fetch();

        self::assertStringContainsString('🔎 Extracting vendor libraries from root package... Done', $output);
        self::assertStringContainsString('✍️ Creating composer.json file for extracted vendor libraries... Done', $output);

        $actual = $this->parseComposerJson($librariesPath.'/composer.json');
        $requires = $actual->getRequires();

        self::assertCount(1, $requires);
        self::assertArrayHasKey('eliashaeussler/sse', $requires);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleDisplaysDependencyExtractionProblems(): void
    {
        $rootPath = $this->createTemporaryFixture('invalid-libs');
        $librariesPath = $rootPath.'/libs';
        $subject = $this->createSubject($rootPath);

        $this->filesystem->remove($librariesPath);

        $subject->bundle(failOnExtractionProblems: false);

        $output = $this->output->fetch();

        self::assertStringContainsString('🔎 Extracting vendor libraries from root package... Failed', $output);
        self::assertStringContainsString('Could not resolve a dedicated Composer package for the requirement "eliashaeussler/sssseee".', $output);
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfProblemsOccurDuringDependencyExtraction(): void
    {
        $rootPath = $this->createTemporaryFixture('invalid-libs');
        $librariesPath = $rootPath.'/libs';
        $subject = $this->createSubject($rootPath);

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
        }
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfPathToVendorLibrariesDoesNotExist(): void
    {
        $rootPath = $this->createTemporaryFixture();
        $subject = $this->createSubject($rootPath, 'foo');

        $this->expectExceptionObject(
            new Src\Exception\DirectoryDoesNotExist($rootPath.'/foo'),
        );

        $subject->bundle(extractDependencies: false);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleExcludesFilesFromLibsClassMapInComposerJsonFile(): void
    {
        $composerJson = $this->rootPath.'/composer.json';

        try {
            $this->subject->bundle(false, true, false, ['baz.php']);
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('📦 Installing vendor libraries... Done', $output);
            self::assertStringContainsString('🪄 Parsing autoloads from composer.json files... Done', $output);
            self::assertStringContainsString('⛔ Removed baz.php from class map', $output);
            self::assertStringContainsString('🎊 Dumping merged autoload configuration... Done', $output);
            self::assertFileExists($composerJson);

            $actual = $this->parseComposerJson($composerJson);

            // Assert that libs/baz.php is not included in final class map
            self::assertSame(['baz.php'], $actual->getAutoload()['classmap'] ?? null);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleShowsWarningIfFileToExcludeFromClassMapIsNotIncludedInClassMap(): void
    {
        $this->output->setVerbosity(Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE);

        try {
            $this->subject->bundle(false, true, false, ['foo.php']);
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('⚠️ File foo.php not found in class map', $output);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleBacksUpSourceFiles(): void
    {
        $composerJson = $this->rootPath.'/composer.json';
        $composerJsonBackup = $this->rootPath.'/composer.json.bak';

        $this->filesystem->remove($composerJsonBackup);

        self::assertFileExists($composerJson);
        self::assertFileDoesNotExist($composerJsonBackup);

        $composerJsonSource = file_get_contents($composerJson);

        try {
            $this->subject->bundle(false, true, true);
        } finally {
            // Restore original source files
            file_put_contents($composerJson, $composerJsonSource);

            self::assertFileExists($composerJsonBackup);
            self::assertIsString($composerJsonSource);
            self::assertStringEqualsFile($composerJsonBackup, $composerJsonSource);

            $output = $this->output->fetch();

            self::assertStringContainsString('📦 Installing vendor libraries... Done', $output);
            self::assertStringContainsString('🪄 Parsing autoloads from composer.json files... Done', $output);
            self::assertStringContainsString('🦖 Backing up source files... Done', $output);
            self::assertStringContainsString('🎊 Dumping merged autoload configuration... Done', $output);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleDumpsMergedAutoloadConfiguration(): void
    {
        $composerJson = $this->rootPath.'/composer.json';

        try {
            $this->subject->bundle();
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('📦 Installing vendor libraries... Done', $output);
            self::assertStringContainsString('🪄 Parsing autoloads from composer.json files... Done', $output);
            self::assertStringContainsString('🎊 Dumping merged autoload configuration... Done', $output);

            $actual = $this->parseComposerJson($composerJson);

            self::assertIsArray($actual->getAutoload()['classmap'] ?? null);
            self::assertCount(2, $actual->getAutoload()['classmap']);
            self::assertIsArray($actual->getAutoload()['psr-4'] ?? null);
            self::assertGreaterThan(1, count($actual->getAutoload()['psr-4']));
            self::assertIsArray($actual->getAutoload()['files'] ?? null);
            self::assertGreaterThan(2, count($actual->getAutoload()['files']));
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleWritesMetadataToRootComposerJson(): void
    {
        $composerJson = $this->rootPath.'/composer.json';

        try {
            $this->subject->bundle();
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('📦 Installing vendor libraries... Done', $output);
            self::assertStringContainsString('🪄 Parsing autoloads from composer.json files... Done', $output);
            self::assertStringContainsString('✍️ Writing dependency metadata to composer.json file... Done', $output);

            $expected = [
                'extension-key' => 'test',
                'vendor-libraries' => [
                    'root-path' => 'libs',
                ],
            ];

            $actual = $this->parseComposerJson($composerJson);

            self::assertSame($expected, $actual->getExtra()['typo3/cms'] ?? null);
        }
    }

    private function createSubject(string $rootPath, string $librariesPath = 'libs'): Src\Bundler\AutoloadBundler
    {
        return new Src\Bundler\AutoloadBundler($rootPath, $librariesPath, $this->output);
    }
}
