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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Command;

use Composer\Console\Application;
use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;

use function dirname;

/**
 * BundleAutoloadCommandTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Command\BundleAutoloadCommand::class)]
final class BundleAutoloadCommandTest extends Framework\TestCase
{
    private Console\Tester\CommandTester $commandTester;
    private Filesystem\Filesystem $filesystem;

    public function setUp(): void
    {
        $application = new Application();
        $command = new Src\Command\BundleAutoloadCommand();
        $command->setApplication($application);

        $this->commandTester = new Console\Tester\CommandTester($command);
        $this->filesystem = new Filesystem\Filesystem();
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
    public function executeUsesConfigurationOptionsIfNoCommandOptionsAreGiven(): void
    {
        $workingDirectory = getcwd();

        self::assertIsString($workingDirectory);

        $libsDir = $workingDirectory.'/Resources/Private/Libs';

        // Exception is intended, it shows that default config options are used
        $this->expectExceptionObject(
            new Src\Exception\DirectoryDoesNotExist($libsDir),
        );

        $this->commandTester->execute([]);
    }

    #[Framework\Attributes\Test]
    public function executeThrowsExceptionIfTargetFileAlreadyExistsAndShouldNotBeOverwritten(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures/Extensions/valid';

        // Make sure file exists
        $this->filesystem->touch($rootPath.'/ext_emconf_modified.php');

        $this->commandTester->setInputs(['no']);

        $this->expectExceptionObject(
            new Src\Exception\FileAlreadyExists($rootPath.'/ext_emconf_modified.php'),
        );

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
        ]);
    }

    #[Framework\Attributes\Test]
    public function executeOverwritesTargetFileIfAlreadyExists(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures/Extensions/valid';

        // Clear target file
        $this->filesystem->dumpFile($rootPath.'/ext_emconf_modified.php', '');

        $this->commandTester->setInputs(['yes']);

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
        ]);

        self::assertStringNotEqualsFile($rootPath.'/ext_emconf_modified.php', '');
        self::assertStringContainsString(
            'Successfully bundled autoload configurations',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeOverwritesTargetWithoutAskingIfOverwriteOptionIsSet(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures/Extensions/valid';

        // Clear target file
        $this->filesystem->dumpFile($rootPath.'/ext_emconf_modified.php', '');

        // Set inputs to test if user interaction is *not* triggered
        $this->commandTester->setInputs(['no']);

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--overwrite' => true,
        ]);

        self::assertStringNotEqualsFile($rootPath.'/ext_emconf_modified.php', '');
        self::assertStringContainsString(
            'Successfully bundled autoload configurations',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeUsesLibsDirFromCommandArgument(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures/Extensions/valid';

        $this->expectExceptionObject(
            new Src\Exception\DirectoryDoesNotExist($rootPath.'/foo'),
        );

        $this->commandTester->execute([
            'libs-dir' => 'foo',
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
        ]);
    }

    #[Framework\Attributes\Test]
    public function executeUsesDropComposerAutoloadOptionFromCommandOption(): void
    {
        $sourcePath = dirname(__DIR__).'/Fixtures/Extensions/valid';
        $rootPath = dirname(__DIR__).'/Fixtures/Extensions/valid-temporary';

        $this->filesystem->remove($rootPath);
        $this->filesystem->mirror($sourcePath, $rootPath);

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--drop-composer-autoload' => true,
            '--overwrite' => true,
        ]);

        self::assertJsonStringEqualsJsonFile($rootPath.'/composer.json', '{}');
    }

    #[Framework\Attributes\Test]
    public function executeUsesTargetFileOptionFromCommandOption(): void
    {
        $sourcePath = dirname(__DIR__).'/Fixtures/Extensions/valid';
        $rootPath = dirname(__DIR__).'/Fixtures/Extensions/valid-temporary';

        $this->filesystem->remove($rootPath);
        $this->filesystem->mirror($sourcePath, $rootPath);
        $this->filesystem->remove($rootPath.'/foo.php');

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--target-file' => 'foo.php',
        ]);

        self::assertFileExists($rootPath.'/foo.php');
        self::assertStringContainsString(
            'Successfully bundled autoload configurations in "foo.php".',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeUsesBackupSourcesOptionFromCommandOption(): void
    {
        $sourcePath = dirname(__DIR__).'/Fixtures/Extensions/valid';
        $rootPath = dirname(__DIR__).'/Fixtures/Extensions/valid-temporary';

        $this->filesystem->remove($rootPath);
        $this->filesystem->mirror($sourcePath, $rootPath);
        $this->filesystem->remove([$rootPath.'/composer.json.bak', $rootPath.'/ext_emconf.php.bak']);

        $this->commandTester->execute([
            '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
            '--drop-composer-autoload' => true,
            '--target-file' => 'ext_emconf.php',
            '--backup-sources' => false,
            '--overwrite' => true,
        ]);

        self::assertFileDoesNotExist($rootPath.'/composer.json.bak');
        self::assertFileDoesNotExist($rootPath.'/ext_emconf.bak');
    }
}
