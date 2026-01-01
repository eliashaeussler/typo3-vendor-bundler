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
use Generator;
use PHPUnit\Framework;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;

use function dirname;

/**
 * ExtractDependenciesCommandTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Command\ExtractDependenciesCommand::class)]
final class ExtractDependenciesCommandTest extends Framework\TestCase
{
    private Console\Tester\CommandTester $commandTester;
    private Filesystem\Filesystem $filesystem;

    public function setUp(): void
    {
        $application = new Application();
        $command = new Src\Command\ExtractDependenciesCommand();
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
    public function executeFailsIfPathToVendorLibrariesIsInvalidAndDumpOptionIsSet(): void
    {
        $this->commandTester->execute([
            'libs-dir' => '',
            '--dump-to-file' => true,
        ]);

        self::assertSame(Console\Command\Command::INVALID, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            'Please provide a valid path to vendor libraries',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeFailsIfRootComposerInstanceCannotBeCreated(): void
    {
        $this->commandTester->execute([
            '--config' => dirname(__DIR__).'/Fixtures/Extensions/invalid-composer-file/typo3-vendor-bundler.yaml',
        ]);

        self::assertSame(Console\Command\Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            'Could not initialize a Composer instance for the root package.',
            $this->commandTester->getDisplay(),
        );
    }

    /**
     * @return Generator<string, array{array<string, bool>, Console\Output\OutputInterface::VERBOSITY_*, bool}>
     */
    public static function executeDisplaysExtractedDependenciesDataProvider(): Generator
    {
        yield 'no options, verbosity normal' => [
            [],
            Console\Output\OutputInterface::VERBOSITY_NORMAL,
            true,
        ];
        yield 'dump option, verbosity normal' => [
            ['--dump-to-file' => true],
            Console\Output\OutputInterface::VERBOSITY_NORMAL,
            false,
        ];
        yield 'dump option, verbosity verbose' => [
            ['--dump-to-file' => true],
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
            true,
        ];
        yield 'print option, verbosity normal' => [
            ['--print-file-contents' => true],
            Console\Output\OutputInterface::VERBOSITY_NORMAL,
            false,
        ];
        yield 'print option, verbosity verbose' => [
            ['--print-file-contents' => true],
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
            true,
        ];
    }

    /**
     * @param array<string, bool>                         $input
     * @param Console\Output\OutputInterface::VERBOSITY_* $verbosity
     */
    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('executeDisplaysExtractedDependenciesDataProvider')]
    public function executeDisplaysExtractedDependencies(array $input, int $verbosity, bool $expected): void
    {
        $input['--config'] = dirname(__DIR__).'/Fixtures/Extensions/valid-no-libs/typo3-vendor-bundler.yaml';

        $this->commandTester->execute($input, ['verbosity' => $verbosity]);

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('🔎 Extracting dependencies from root package... Done', $output);
        self::assertStringNotContainsString('Excluded psr/http-message', $output);

        if ($expected) {
            self::assertStringContainsString('Extracted eliashaeussler/sse', $output);
        } else {
            self::assertStringNotContainsString('Extracted eliashaeussler/sse', $output);
        }
    }

    #[Framework\Attributes\Test]
    public function executeDisplaysExcludedDependenciesOnVeryVerboseOutput(): void
    {
        $this->commandTester->execute(
            [
                '--config' => dirname(__DIR__).'/Fixtures/Extensions/valid-no-libs/typo3-vendor-bundler.yaml',
            ],
            [
                'verbosity' => Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE,
            ],
        );

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('🔎 Extracting dependencies from root package... Done', $output);
        self::assertStringContainsString('Excluded psr/http-message', $output);
    }

    #[Framework\Attributes\Test]
    public function executeFailsOnExtractionProblems(): void
    {
        $this->commandTester->execute(
            [
                '--config' => dirname(__DIR__).'/Fixtures/Extensions/invalid-libs-constraint/typo3-vendor-bundler.yaml',
            ],
        );

        self::assertSame(Console\Command\Command::FAILURE, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('🔎 Extracting dependencies from root package... Failed', $output);
        self::assertStringContainsString(
            'Could not find a matching version for the Composer package "eliashaeussler/cache-warmup".',
            $output,
        );
    }

    #[Framework\Attributes\Test]
    public function executeDoesNotFailOnExtractionProblemsIfNoFailOptionIsGiven(): void
    {
        $this->commandTester->execute(
            [
                '--config' => dirname(__DIR__).'/Fixtures/Extensions/invalid-libs-constraint/typo3-vendor-bundler.yaml',
                '--fail' => false,
            ],
        );

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('🔎 Extracting dependencies from root package... Failed', $output);
        self::assertStringContainsString('Dependency extraction finished with problems.', $output);
    }

    #[Framework\Attributes\Test]
    public function executeFailsIfNoVendorLibrariesWereFoundInRootComposerJson(): void
    {
        $this->commandTester->execute(
            [
                '--config' => dirname(__DIR__).'/Fixtures/Extensions/invalid-libs/typo3-vendor-bundler.yaml',
                '--fail' => false,
            ],
        );

        self::assertSame(Console\Command\Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            'No vendor libraries found in composer.json file.',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeDisplaysComposerJsonFileContentsIfPrintOptionIsGiven(): void
    {
        $this->commandTester->execute(
            [
                '--config' => dirname(__DIR__).'/Fixtures/Extensions/valid-no-libs/typo3-vendor-bundler.yaml',
                '--print-file-contents' => true,
            ],
        );

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('✍️ Building composer.json file contents... Done', $output);
        self::assertStringContainsString('"eliashaeussler/sse": "', $output);
    }

    #[Framework\Attributes\Test]
    public function executeWritesComposerJsonToFileIfDumpOptionIsGiven(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures/Extensions/valid-no-libs';

        $this->filesystem->remove($rootPath.'/libs');

        $this->commandTester->execute(
            [
                '--config' => $rootPath.'/typo3-vendor-bundler.yaml',
                '--dump-to-file' => true,
            ],
        );

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('✍️ Creating composer.json file for extracted vendor libraries... Done', $output);
        self::assertStringContainsString('Successfully extracted and dumped dependencies', $output);
        self::assertFileExists($rootPath.'/libs/composer.json');
    }
}
