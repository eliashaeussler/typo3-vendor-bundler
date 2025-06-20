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

use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;

use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function trim;

/**
 * AutoloadBundlerTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Bundler\AutoloadBundler::class)]
final class AutoloadBundlerTest extends Framework\TestCase
{
    private Src\Resource\ExtEmConfParser $extEmConfParser;
    private Filesystem\Filesystem $filesystem;
    private Console\Output\BufferedOutput $output;
    private Src\Bundler\AutoloadBundler $subject;

    public function setUp(): void
    {
        $this->extEmConfParser = new Src\Resource\ExtEmConfParser();
        $this->filesystem = new Filesystem\Filesystem();
        $this->output = new Console\Output\BufferedOutput(Console\Output\OutputInterface::VERBOSITY_VERBOSE);
        $this->subject = $this->createSubject('valid');
    }

    #[Framework\Attributes\Test]
    public function constructorThrowsExceptionIfPathToVendorLibrariesDoesNotExist(): void
    {
        $librariesPath = $this->getFixturePath('valid').'/foo';

        $this->expectExceptionObject(
            new Src\Exception\DirectoryDoesNotExist($librariesPath),
        );

        $this->createSubject('valid', 'foo');
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfDeclarationFileHasInvalidAutoloadSection(): void
    {
        $declarationFile = $this->getFixturePath('invalid-autoload').'/ext_emconf.php';
        $subject = $this->createSubject('invalid-autoload');

        $this->expectExceptionObject(
            new Src\Exception\DeclarationFileIsInvalid($declarationFile, '[autoload]'),
        );

        try {
            $subject->bundle();
        } finally {
            self::assertSame(
                '🔍 Parsing ext_emconf.php file... Failed',
                trim($this->output->fetch()),
            );
        }
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfDeclarationFileHasInvalidClassMapSection(): void
    {
        $declarationFile = $this->getFixturePath('invalid-classmap').'/ext_emconf.php';
        $subject = $this->createSubject('invalid-classmap');

        $this->expectExceptionObject(
            new Src\Exception\DeclarationFileIsInvalid($declarationFile, '[autoload][classmap]'),
        );

        try {
            $subject->bundle();
        } finally {
            self::assertSame(
                '🔍 Parsing ext_emconf.php file... Failed',
                trim($this->output->fetch()),
            );
        }
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfDeclarationFileHasInvalidNamespacesSection(): void
    {
        $declarationFile = $this->getFixturePath('invalid-namespaces').'/ext_emconf.php';
        $subject = $this->createSubject('invalid-namespaces');

        $this->expectExceptionObject(
            new Src\Exception\DeclarationFileIsInvalid($declarationFile, '[autoload][psr-4]'),
        );

        try {
            $subject->bundle();
        } finally {
            self::assertSame(
                '🔍 Parsing ext_emconf.php file... Failed',
                trim($this->output->fetch()),
            );
        }
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfComposerFileIsInvalid(): void
    {
        $librariesPath = $this->getFixturePath('invalid-composer-file').'/libs';
        $subject = $this->createSubject('invalid-composer-file');

        $this->expectExceptionObject(
            new Src\Exception\CannotInstallComposerDependencies($librariesPath),
        );

        try {
            $subject->bundle();
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🔍 Parsing ext_emconf.php file... Done', $output);
            self::assertStringContainsString('🍄 Loading class map from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries...', $output);
            self::assertStringContainsString(
                'Your requirements could not be resolved to an installable set of packages.',
                $output,
            );
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleExcludesFilesFromLibsClassMap(): void
    {
        $targetFile = $this->getFixturePath('valid').'/ext_emconf_modified.php';

        try {
            $this->subject->bundle(
                'ext_emconf_modified.php',
                false,
                false,
                true,
                [
                    'vendor/composer/InstalledVersions.php',
                ],
            );
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🔍 Parsing ext_emconf.php file... Done', $output);
            self::assertStringContainsString('🍄 Loading class map from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString(
                '⛔ Removing "vendor/composer/InstalledVersions.php" from class map... Done',
                $output,
            );

            $actual = $this->extEmConfParser->parse($targetFile);

            self::assertIsArray($actual['autoload'] ?? null);
            self::assertIsArray($actual['autoload']['classmap'] ?? null);
            self::assertNotContains('libs/vendor/composer/InstalledVersions.php', $actual['autoload']['classmap']);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleShowsErrorIfFileToExcludeFromClassMapIsNotIncludedInClassMap(): void
    {
        try {
            $this->subject->bundle(
                'ext_emconf_modified.php',
                false,
                false,
                true,
                [
                    'foo.php',
                ],
            );
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🔍 Parsing ext_emconf.php file... Done', $output);
            self::assertStringContainsString('🍄 Loading class map from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
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
            $subject->bundle();
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🔍 Parsing ext_emconf.php file... Done', $output);
            self::assertStringContainsString('🍄 Loading class map from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString('🌱 Loading class map from root package... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('🍄 Loading PSR-4 namespaces from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Loading PSR-4 namespaces from root package... Failed', $output);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleFlattensSingleValueArrayOfNamespacePaths(): void
    {
        $targetFile = $this->getFixturePath('valid-single-array-namespace-path').'/ext_emconf_modified.php';
        $subject = $this->createSubject('valid-single-array-namespace-path');

        try {
            $subject->bundle('ext_emconf_modified.php', false, false, true);
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🔍 Parsing ext_emconf.php file... Done', $output);
            self::assertStringContainsString('🍄 Loading class map from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString('🌱 Loading class map from root package... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('🍄 Loading PSR-4 namespaces from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Loading PSR-4 namespaces from root package... Done', $output);
            self::assertStringContainsString('♨️ Merging PSR-4 namespaces... Done', $output);
            self::assertStringContainsString('🎊 Dumping merged autoload configuration... Done', $output);

            $actual = $this->extEmConfParser->parse($targetFile);

            self::assertIsArray($actual['autoload'] ?? null);
            self::assertIsArray($actual['autoload']['psr-4'] ?? null);
            self::assertCount(2, $actual['autoload']['psr-4']);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleThrowsExceptionIfTargetFileAlreadyExists(): void
    {
        $targetFile = $this->getFixturePath('valid').'/ext_emconf.php';

        $this->expectExceptionObject(
            new Src\Exception\FileAlreadyExists($targetFile),
        );

        try {
            $this->subject->bundle();
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🔍 Parsing ext_emconf.php file... Done', $output);
            self::assertStringContainsString('🍄 Loading class map from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString('🌱 Loading class map from root package... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('🍄 Loading PSR-4 namespaces from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Loading PSR-4 namespaces from root package... Done', $output);
            self::assertStringContainsString('♨️ Merging PSR-4 namespaces... Done', $output);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleBacksUpSourceFiles(): void
    {
        $fixturePath = $this->getFixturePath('valid');
        $extEmConf = $fixturePath.'/ext_emconf.php';
        $extEmConfBackup = $fixturePath.'/ext_emconf.php.bak';
        $composerJson = $fixturePath.'/composer.json';
        $composerJsonBackup = $fixturePath.'/composer.json.bak';

        $this->filesystem->remove($extEmConfBackup);
        $this->filesystem->remove($composerJsonBackup);

        self::assertFileExists($extEmConf);
        self::assertFileDoesNotExist($extEmConfBackup);

        self::assertFileExists($composerJson);
        self::assertFileDoesNotExist($composerJsonBackup);

        $extEmConfSource = file_get_contents($extEmConf);
        $composerJsonSource = file_get_contents($composerJson);

        try {
            $this->subject->bundle('ext_emconf.php', true, true, true);
        } finally {
            // Restore original source files
            file_put_contents($extEmConf, $extEmConfSource);
            file_put_contents($composerJson, $composerJsonSource);

            self::assertFileExists($extEmConfBackup);
            self::assertIsString($extEmConfSource);
            self::assertStringEqualsFile($extEmConfBackup, $extEmConfSource);

            self::assertFileExists($composerJsonBackup);
            self::assertIsString($composerJsonSource);
            self::assertStringEqualsFile($composerJsonBackup, $composerJsonSource);

            $output = $this->output->fetch();

            self::assertStringContainsString('🔍 Parsing ext_emconf.php file... Done', $output);
            self::assertStringContainsString('🍄 Loading class map from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString('🌱 Loading class map from root package... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('🍄 Loading PSR-4 namespaces from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Loading PSR-4 namespaces from root package... Done', $output);
            self::assertStringContainsString('♨️ Merging PSR-4 namespaces... Done', $output);
            self::assertStringContainsString('🦖 Backing up source files... Done', $output);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleDumpsMergedAutoloadConfiguration(): void
    {
        $targetFile = $this->getFixturePath('valid').'/ext_emconf_modified.php';

        try {
            $this->subject->bundle('ext_emconf_modified.php', false, false, true);
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('🔍 Parsing ext_emconf.php file... Done', $output);
            self::assertStringContainsString('🍄 Loading class map from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString('🌱 Loading class map from root package... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('🍄 Loading PSR-4 namespaces from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Loading PSR-4 namespaces from root package... Done', $output);
            self::assertStringContainsString('♨️ Merging PSR-4 namespaces... Done', $output);
            self::assertStringContainsString('🎊 Dumping merged autoload configuration... Done', $output);

            $actual = $this->extEmConfParser->parse($targetFile);

            self::assertIsArray($actual['autoload'] ?? null);
            self::assertIsArray($actual['autoload']['classmap'] ?? null);
            self::assertGreaterThan(2, count($actual['autoload']['classmap']));
            self::assertIsArray($actual['autoload']['psr-4'] ?? null);
            self::assertCount(2, $actual['autoload']['psr-4']);
        }
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleRemovesAutoloadSectionFromRootComposerJson(): void
    {
        $fixturePath = $this->getFixturePath('valid');
        $composerJson = $fixturePath.'/composer.json';

        self::assertFileExists($composerJson);

        $composerJsonSource = file_get_contents($composerJson);

        try {
            $this->subject->bundle('ext_emconf_modified.php', overwriteExistingTargetFile: true);
        } finally {
            $actual = file_get_contents($composerJson);

            // Restore original source file
            file_put_contents($composerJson, $composerJsonSource);

            self::assertIsString($composerJsonSource);

            $output = $this->output->fetch();

            self::assertStringContainsString('🔍 Parsing ext_emconf.php file... Done', $output);
            self::assertStringContainsString('🍄 Loading class map from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Building class map from vendor libraries... Done', $output);
            self::assertStringContainsString('🌱 Loading class map from root package... Done', $output);
            self::assertStringContainsString('♨️ Merging class maps... Done', $output);
            self::assertStringContainsString('🍄 Loading PSR-4 namespaces from ext_emconf.php... Done', $output);
            self::assertStringContainsString('🌱 Loading PSR-4 namespaces from root package... Done', $output);
            self::assertStringContainsString('♨️ Merging PSR-4 namespaces... Done', $output);
            self::assertStringContainsString('✂️ Removing autoload section from composer.json... Done', $output);

            self::assertIsString($actual);
            self::assertJsonStringEqualsJsonString('{}', $actual);
        }
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
