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

namespace EliasHaeussler\Typo3VendorBundler\Command;

use CycloneDX\Core;
use EliasHaeussler\Typo3VendorBundler\Bundler;
use EliasHaeussler\Typo3VendorBundler\Exception;
use Symfony\Component\Console;
use Throwable;

use function getcwd;
use function sprintf;
use function trim;

/**
 * BundleDependenciesCommand.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final class BundleDependenciesCommand extends AbstractConfigurationAwareCommand
{
    public function __construct()
    {
        parent::__construct('bundle-dependencies');
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Bundle dependency information of vendor libraries');

        $this->addArgument(
            'libs-dir',
            Console\Input\InputArgument::OPTIONAL,
            'Path to vendor libraries (either absolute or relative to working directory)',
        );

        $this->addOption(
            'sbom-file',
            'f',
            Console\Input\InputOption::VALUE_REQUIRED,
            'File where to dump the generated SBOM',
        );
        $this->addOption(
            'sbom-version',
            'b',
            Console\Input\InputOption::VALUE_REQUIRED,
            sprintf(
                'Version to use when dumping the generated SBOM (defaults to "%s")',
                Core\Spec\Version::v1dot7->value,
            ),
        );
        $this->addOption(
            'dev',
            null,
            Console\Input\InputOption::VALUE_NONE | Console\Input\InputOption::VALUE_NEGATABLE,
            'Include development dependencies in the generated SBOM file',
        );
        $this->addOption(
            'overwrite',
            'o',
            Console\Input\InputOption::VALUE_NONE | Console\Input\InputOption::VALUE_NEGATABLE,
            'Force overwriting the given SBOM file, if it already exists',
        );
        $this->addOption(
            'extract',
            'x',
            Console\Input\InputOption::VALUE_NONE | Console\Input\InputOption::VALUE_NEGATABLE,
            'Auto-detect and extract vendor libraries from root composer.json',
        );
        $this->addOption(
            'fail',
            null,
            Console\Input\InputOption::VALUE_NONE | Console\Input\InputOption::VALUE_NEGATABLE,
            'Fail execution if dependency extraction finishes with problems',
        );
    }

    /**
     * @throws Exception\DirectoryDoesNotExist
     * @throws Exception\FileAlreadyExists
     * @throws Throwable
     */
    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): int
    {
        $rootPath = (string) getcwd();
        $configFile = $input->getOption('config');
        $config = $this->readConfigFile($configFile, $rootPath);

        // Exit if config cannot be read
        if (null === $config) {
            return self::INVALID;
        }

        $rootPath = $config->rootPath() ?? $rootPath;
        $libsDir = $input->getArgument('libs-dir') ?? $config->pathToVendorLibraries();
        $extract = $input->getOption('extract') ?? $config->dependencyExtraction()->enabled() ?? true;
        $fail = $input->getOption('fail') ?? $config->dependencyExtraction()->failOnProblems() ?? true;
        $sbomFile = $input->getOption('sbom-file') ?? $config->dependencies()->sbom()->file();
        $includeDev = $input->getOption('dev') ?? $config->dependencies()->sbom()->includeDev() ?? true;
        $overwrite = $input->getOption('overwrite') ?? $config->dependencies()->sbom()->overwrite() ?? false;

        // Read SBOM version
        if (($sbomVersion = $input->getOption('sbom-version')) !== null) {
            $sbomVersion = Core\Spec\Version::tryFrom($sbomVersion);
        } else {
            $sbomVersion = $config->dependencies()->sbom()->version();
        }

        // Exit if SBOM version is not supported
        if (null === $sbomVersion) {
            $this->io->error('The given CycloneDX version is not supported.');

            return self::INVALID;
        }

        // Exit if libs directory is invalid
        if ('' === trim($libsDir)) {
            $this->io->error('Please provide a valid path to vendor libraries.');

            return self::INVALID;
        }

        $dependencyBundler = new Bundler\DependencyBundler($rootPath, $libsDir, $this->io);

        try {
            $dependencies = $dependencyBundler->bundle($sbomFile, $sbomVersion, $extract, $fail, $includeDev, $overwrite);
        } catch (Exception\FileAlreadyExists $exception) {
            if (false === $input->getOption('overwrite')
                || !$this->io->confirm('SBOM file already exists. Overwrite file?', false)
            ) {
                throw $exception;
            }

            $dependencies = $dependencyBundler->bundle($sbomFile, $sbomVersion, $extract, $fail, $includeDev, true);
        }

        $this->io->success(
            sprintf('Successfully bundled dependency information in "%s".', $dependencies->sbomFile(true)),
        );

        return self::SUCCESS;
    }
}
