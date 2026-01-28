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

/**
 * BundleAutoloadCommandTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Command\BundleAutoloadCommand::class)]
final class BundleAutoloadCommandTest extends Tests\ExtensionFixtureBasedTestCase
{
    private Console\Tester\CommandTester $commandTester;

    public function setUp(): void
    {
        parent::setUp();

        $application = new Application();
        $command = new Src\Command\BundleAutoloadCommand();
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
    #[Framework\Attributes\WithoutErrorHandler]
    public function executeUsesConfigurationOptionsIfNoCommandOptionsAreGiven(): void
    {
        $temporaryDirectory = $this->createTemporaryDirectory();

        $this->filesystem->dumpFile($temporaryDirectory.'/composer.json', '{}');

        Src\Helper\FilesystemHelper::executeInDirectory(
            $temporaryDirectory,
            fn () => $this->commandTester->execute([]),
        );

        self::assertJsonStringNotEqualsJsonFile($temporaryDirectory.'/composer.json', '{}');
    }

    #[Framework\Attributes\Test]
    public function executeUsesLibsDirFromCommandArgument(): void
    {
        $rootPath = self::getFixturePath();

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
    public function executeUsesBackupSourcesOptionFromCommandOption(): void
    {
        $rootPath = $this->createTemporaryFixture();

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--backup-sources' => false,
        ]);

        self::assertFileDoesNotExist($rootPath.'/composer.json.bak');
    }
}
