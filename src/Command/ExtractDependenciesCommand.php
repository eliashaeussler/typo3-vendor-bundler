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

namespace EliasHaeussler\Typo3VendorBundler\Command;

use Composer\Composer;
use Composer\Factory;
use Composer\IO;
use EliasHaeussler\TaskRunner;
use EliasHaeussler\Typo3VendorBundler\Resource;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;
use Throwable;

use function file_get_contents;
use function sprintf;

/**
 * ExtractDependenciesCommand.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final class ExtractDependenciesCommand extends AbstractConfigurationAwareCommand
{
    private readonly Filesystem\Filesystem $filesystem;
    private TaskRunner\TaskRunner $taskRunner;

    public function __construct()
    {
        parent::__construct('extract-dependencies');

        $this->filesystem = new Filesystem\Filesystem();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setAliases(['extract']);
        $this->setDescription('Extract vendor libraries to bundle from composer.json');

        $this->addArgument(
            'libs-dir',
            Console\Input\InputArgument::OPTIONAL,
            'Path to vendor libraries (either absolute or relative to working directory)',
        );

        $this->addOption(
            'fail',
            'f',
            Console\Input\InputOption::VALUE_NONE | Console\Input\InputOption::VALUE_NEGATABLE,
            'Fail execution if dependency extraction finishes with problems',
        );
        $this->addOption(
            'print-file-contents',
            'p',
            Console\Input\InputOption::VALUE_NONE,
            'Print contents of composer.json file instead of dumping it to a file',
        );
        $this->addOption(
            'dump-to-file',
            'w',
            Console\Input\InputOption::VALUE_NONE,
            'Dump extracted dependencies to target composer.json file',
        );
    }

    protected function initialize(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->taskRunner = new TaskRunner\TaskRunner($this->io);
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): int
    {
        $configFile = $input->getOption('config');
        $config = $this->readConfigFile($configFile, (string) getcwd());

        // Exit if config cannot be read
        if (null === $config) {
            return self::INVALID;
        }

        $libsDir = $input->getArgument('libs-dir') ?? $config->pathToVendorLibraries();
        $fail = $input->getOption('fail') ?? $config->dependencyExtraction()->failOnProblems() ?? true;
        $print = $input->getOption('print-file-contents');
        $dump = $input->getOption('dump-to-file');

        // Exit if libs directory is invalid
        if ($dump && '' === trim($libsDir)) {
            $this->io->error('Please provide a valid path to vendor libraries.');

            return self::INVALID;
        }

        $rootPath = (string) $config->rootPath();
        $problems = [];

        try {
            $composer = Factory::create(new IO\NullIO(), Filesystem\Path::join($rootPath, 'composer.json'));
        } catch (Throwable) {
            $this->io->error('Could not initialize a Composer instance for the root package.');

            return self::FAILURE;
        }

        // Extract dependencies using dependency extractor component
        $dependencySet = $this->extractDependencies($composer, $dump, $print, $problems);

        // List extraction problems
        if ([] !== $problems) {
            if ($fail) {
                $this->io->error('Failed to extract some dependencies from composer.json file.');
            } else {
                $this->io->warning('Dependency extraction finished with problems.');
            }

            $this->io->listing($problems);

            // Fail execution if configured to do so
            if ($fail) {
                return self::FAILURE;
            }
        }

        // Fail if no vendor libraries were found
        if ([] === $dependencySet->requiredPackages) {
            $this->io->warning('No vendor libraries found in composer.json file.');

            return self::FAILURE;
        }

        // Build and print composer.json from extracted dependencies
        if ($print) {
            $this->printFileContents($dependencySet, $composer);
        }

        // Dump extracted dependencies to composer.json file
        if ($dump) {
            $this->dumpToFile($dependencySet, $composer, $libsDir, $rootPath);

            return self::SUCCESS;
        }

        $this->io->success('Successfully extracted dependencies from composer.json file.');
        $this->io->writeln('💡 Run this command again with <comment>--dump-to-file</comment> to persist extracted dependencies.');

        return self::SUCCESS;
    }

    /**
     * @param list<string> $problems
     */
    private function extractDependencies(Composer $composer, bool $dump, bool $print, array &$problems = []): Resource\DependencySet
    {
        return $this->taskRunner->run(
            '🔎 Extracting dependencies from root package',
            function (TaskRunner\RunnerContext $context) use ($composer, $dump, $print, &$problems) {
                $extractor = new Resource\DependencyExtractor();
                $dependencySet = $extractor->extract($composer);
                $problems = $dependencySet->problems();
                $verbosity = !$dump && !$print ? Console\Output\OutputInterface::VERBOSITY_NORMAL : Console\Output\OutputInterface::VERBOSITY_VERBOSE;

                // Display requirements (only on -v mode or if neither print nor dump are requested)
                foreach ($dependencySet->requirements() as $packageName => $packageVersion) {
                    $context->output->writeln(
                        sprintf(
                            '✅ Extracted <comment>%s</comment> (version: <comment>%s</comment>)',
                            $packageName,
                            $packageVersion,
                        ),
                        $verbosity,
                    );
                }

                // Display exclusions (only on -vv mode)
                foreach ($dependencySet->exclusions() as $packageName => $packageVersion) {
                    $context->output->writeln(
                        sprintf('⛔️ Excluded <comment>%s</comment> (all versions)', $packageName),
                        Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE,
                    );
                }

                // Fail task if extraction problems occured
                if ([] !== $problems) {
                    $context->successful = false;
                }

                return $dependencySet;
            },
        );
    }

    private function printFileContents(Resource\DependencySet $dependencySet, Composer $composer): void
    {
        $this->taskRunner->run(
            '✍️ Building <comment>composer.json</comment> file contents',
            function (TaskRunner\RunnerContext $context) use ($dependencySet, $composer) {
                $filename = $this->filesystem->tempnam(sys_get_temp_dir(), 'typo3-vendor-bundler-', '.json');
                $this->filesystem->dumpFile($filename, '{}');

                try {
                    $dependencySet->dumpToFile($filename, $composer);
                    $fileContents = file_get_contents($filename);

                    if (false === $fileContents) {
                        $context->successful = false;
                    } else {
                        $context->output->write($fileContents);
                    }
                } finally {
                    unlink($filename);
                }
            },
        );
    }

    private function dumpToFile(
        Resource\DependencySet $dependencySet,
        Composer $composer,
        string $libsDir,
        string $rootPath,
    ): void {
        $librariesPath = Filesystem\Path::makeAbsolute($libsDir, $rootPath);
        $filename = Filesystem\Path::join($librariesPath, 'composer.json');

        $this->taskRunner->run(
            '✍️ Creating <comment>composer.json</comment> file for extracted vendor libraries',
            static fn () => $dependencySet->dumpToFile($filename, $composer),
        );

        $this->io->success(
            sprintf(
                'Successfully extracted and dumped dependencies to "%s".',
                Filesystem\Path::makeRelative($filename, $rootPath),
            ),
        );
    }
}
