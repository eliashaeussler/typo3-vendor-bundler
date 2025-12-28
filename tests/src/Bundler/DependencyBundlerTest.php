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
use CycloneDX\Core;
use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;

use function dirname;

/**
 * DependencyBundlerTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Bundler\DependencyBundler::class)]
final class DependencyBundlerTest extends Framework\TestCase
{
    private Filesystem\Filesystem $filesystem;
    private Console\Output\BufferedOutput $output;
    private Src\Bundler\DependencyBundler $subject;

    public function setUp(): void
    {
        $this->filesystem = new Filesystem\Filesystem();
        $this->output = new Console\Output\BufferedOutput(Console\Output\OutputInterface::VERBOSITY_VERBOSE);
        $this->subject = $this->createSubject('valid');
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfUnsupportedCombinationOfVersionAndFormatIsGiven(): void
    {
        $this->expectExceptionObject(
            new Src\Exception\BomFormatIsNotSupported('json', Core\Spec\Version::v1dot1),
        );

        $this->subject->bundle(version: Core\Spec\Version::v1dot1);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function bundleExtractsVendorLibrariesFromRootPackageIfComposerJsonForVendorLibrariesIsMissing(): void
    {
        $librariesPath = $this->getFixturePath('valid-no-libs').'/libs';
        $subject = $this->createSubject('valid-no-libs');

        $this->filesystem->remove($librariesPath);

        $subject->bundle();

        $output = $this->output->fetch();

        self::assertStringContainsString('🔎 Extracting dependencies from root package... Done', $output);
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
    public function bundleThrowsExceptionIfUnsupportedFileFormatIsGiven(): void
    {
        $this->expectExceptionObject(
            new Src\Exception\BomFormatIsNotSupported('php'),
        );

        $this->subject->bundle('sbom.php');
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfComposerFileIsInvalid(): void
    {
        $rootPath = $this->getFixturePath('invalid-dependencies');
        $subject = $this->createSubject('invalid-dependencies');

        $this->expectExceptionObject(
            new Src\Exception\CannotInstallComposerDependencies($rootPath.'/libs'),
        );

        try {
            $subject->bundle();
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('📦 Installing vendor libraries... Failed', $output);
        }
    }

    #[Framework\Attributes\Test]
    public function bundleThrowsExceptionIfSbomFileAlreadyExists(): void
    {
        $sbomFile = $this->getFixturePath('valid').'/libs/sbom.json';

        $this->expectExceptionObject(
            new Src\Exception\FileAlreadyExists($sbomFile),
        );

        $this->subject->bundle();
    }

    #[Framework\Attributes\Test]
    public function bundleDumpsSerializedSbomAsJsonFile(): void
    {
        $sbomFile = $this->getFixturePath('valid').'/libs/sbom_generated.json';

        try {
            $this->subject->bundle('sbom_generated.json', overwrite: true);
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('📦 Installing vendor libraries... Done', $output);
            self::assertStringContainsString('🧩 Generating Software Bill of Materials... Done', $output);
            self::assertFileExists($sbomFile);
        }
    }

    #[Framework\Attributes\Test]
    public function bundleDumpsSerializedSbomAsXmlFile(): void
    {
        $sbomFile = $this->getFixturePath('valid').'/libs/sbom.xml';

        try {
            $this->subject->bundle('sbom.xml', overwrite: true);
        } finally {
            $output = $this->output->fetch();

            self::assertStringContainsString('📦 Installing vendor libraries... Done', $output);
            self::assertStringContainsString('🧩 Generating Software Bill of Materials... Done', $output);
            self::assertFileExists($sbomFile);
        }
    }

    private function parseComposerJson(string $filename): Package\RootPackageInterface
    {
        self::assertFileExists($filename);

        return Factory::create(new IO\NullIO(), $filename)->getPackage();
    }

    private function createSubject(string $extension, string $librariesPath = 'libs'): Src\Bundler\DependencyBundler
    {
        return new Src\Bundler\DependencyBundler(
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
