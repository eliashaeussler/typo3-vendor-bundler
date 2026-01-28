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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Command;

use Composer\Console\Application;
use EliasHaeussler\Typo3VendorBundler as Src;
use EliasHaeussler\Typo3VendorBundler\Tests;
use PHPUnit\Framework;
use Symfony\Component\Console;

use function file_get_contents;

/**
 * BundleDependenciesCommandTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Command\BundleDependenciesCommand::class)]
final class BundleDependenciesCommandTest extends Tests\ExtensionFixtureBasedTestCase
{
    private Console\Tester\CommandTester $commandTester;

    public function setUp(): void
    {
        parent::setUp();

        $application = new Application();
        $command = new Src\Command\BundleDependenciesCommand();
        $command->setApplication($application);

        $this->commandTester = new Console\Tester\CommandTester($command);
    }

    #[Framework\Attributes\Test]
    public function executeFailsIfConfigFileCannotBeRead(): void
    {
        $this->commandTester->execute([
            '--config' => 'foo',
        ]);

        self::assertSame(Console\Command\Command::INVALID, $this->commandTester->getStatusCode());
        self::assertMatchesRegularExpression(
            '/\[ERROR] File ".+\/foo" does not exist\./',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeFailsIfPathToVendorLibrariesIsInvalid(): void
    {
        $this->commandTester->execute([
            'libs-dir' => '',
        ]);

        self::assertSame(Console\Command\Command::INVALID, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            'Please provide a valid path to vendor libraries',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeFailsIfSbomVersionIsInvalid(): void
    {
        $rootPath = self::getFixturePath();

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--sbom-version' => '1.0',
        ]);

        self::assertSame(Console\Command\Command::INVALID, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            'The given CycloneDX version is not supported.',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function executeUsesConfigurationOptionsIfNoCommandOptionsAreGiven(): void
    {
        $temporaryDirectory = $this->createTemporaryDirectory();

        $this->filesystem->dumpFile($temporaryDirectory.'/composer.json', '{}');
        $this->filesystem->dumpFile($temporaryDirectory.'/Resources/Private/Libs/sbom.json', '{}');

        $this->expectExceptionObject(
            new Src\Exception\FileAlreadyExists($temporaryDirectory.'/Resources/Private/Libs/sbom.json'),
        );

        Src\Helper\FilesystemHelper::executeInDirectory(
            $temporaryDirectory,
            fn () => $this->commandTester->execute([]),
        );
    }

    #[Framework\Attributes\Test]
    public function executeThrowsExceptionIfSbomFileAlreadyExistsAndShouldNotBeOverwrittenAsPerInputOption(): void
    {
        $rootPath = $this->createTemporaryFixture();

        // Make sure file exists
        $this->filesystem->touch($rootPath.'/libs/sbom.json');

        $this->expectExceptionObject(
            new Src\Exception\FileAlreadyExists($rootPath.'/libs/sbom.json'),
        );

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--overwrite' => false,
        ]);
    }

    #[Framework\Attributes\Test]
    public function executeThrowsExceptionIfSbomFileAlreadyExistsAndShouldNotBeOverwrittenAsPerConfiguration(): void
    {
        $rootPath = $this->createTemporaryFixture();

        // Make sure file exists
        $this->filesystem->touch($rootPath.'/libs/sbom.json');

        $this->expectExceptionObject(
            new Src\Exception\FileAlreadyExists($rootPath.'/libs/sbom.json'),
        );

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
        ]);
    }

    #[Framework\Attributes\Test]
    public function executeThrowsExceptionIfSbomFileAlreadyExistsAndShouldNotBeOverwrittenAsPerUserInput(): void
    {
        $rootPath = $this->createTemporaryFixture();

        // Make sure file exists
        $this->filesystem->touch($rootPath.'/libs/sbom.json');

        $this->commandTester->setInputs(['no']);

        $this->expectExceptionObject(
            new Src\Exception\FileAlreadyExists($rootPath.'/libs/sbom.json'),
        );

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
        ]);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function executeOverwritesSbomFileIfAlreadyExists(): void
    {
        $rootPath = $this->createTemporaryFixture();

        // Clear SBOM file
        $this->filesystem->dumpFile($rootPath.'/libs/sbom.json', '');

        $this->commandTester->setInputs(['yes']);

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
        ]);

        self::assertStringNotEqualsFile($rootPath.'/libs/sbom.json', '');
        self::assertStringContainsString(
            'Successfully bundled dependency information',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function executeOverwritesSbomFileWithoutAskingIfOverwriteOptionIsSet(): void
    {
        $rootPath = $this->createTemporaryFixture();

        // Clear SBOM file
        $this->filesystem->dumpFile($rootPath.'/libs/sbom.json', '');

        // Set inputs to test if user interaction is *not* triggered
        $this->commandTester->setInputs(['no']);

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--overwrite' => true,
        ]);

        self::assertStringNotEqualsFile($rootPath.'/libs/sbom.json', '');
        self::assertStringContainsString(
            'Successfully bundled dependency information',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeUsesLibsDirFromCommandArgument(): void
    {
        $rootPath = $this->createTemporaryFixture();

        $this->expectExceptionObject(
            new Src\Exception\DirectoryDoesNotExist($rootPath.'/foo'),
        );

        $this->commandTester->execute([
            'libs-dir' => 'foo',
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--extract' => false,
        ]);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function executeUsesSbomFileOptionFromCommandOption(): void
    {
        $rootPath = $this->createTemporaryFixture();

        $this->filesystem->remove($rootPath.'/libs/sbom.json');

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--sbom-file' => 'sbom.json',
        ]);

        self::assertFileExists($rootPath.'/libs/sbom.json');
        self::assertStringContainsString(
            'Successfully bundled dependency information in "libs/sbom.json".',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function executeUsesSbomVersionOptionFromCommandOption(): void
    {
        $rootPath = $this->createTemporaryFixture();

        $this->filesystem->remove($rootPath.'/libs/sbom.json');

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--sbom-version' => '1.5',
        ]);

        self::assertFileExists($rootPath.'/libs/sbom.json');
        self::assertStringContainsString(
            'Successfully bundled dependency information in "libs/sbom.json".',
            $this->commandTester->getDisplay(),
        );
        self::assertStringContainsString(
            '"specVersion":"1.5"',
            (string) file_get_contents($rootPath.'/libs/sbom.json'),
        );
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function executeUsesDevOptionFromCommandOption(): void
    {
        $rootPath = $this->createTemporaryFixture();

        $this->filesystem->remove($rootPath.'/libs/sbom.json');

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--dev' => false,
        ]);

        self::assertFileExists($rootPath.'/libs/sbom.json');
        self::assertStringContainsString(
            'Successfully bundled dependency information in "libs/sbom.json".',
            $this->commandTester->getDisplay(),
        );
        self::assertStringNotContainsString(
            'phpunit/phpunit',
            (string) file_get_contents($rootPath.'/libs/sbom.json'),
        );
    }
}
