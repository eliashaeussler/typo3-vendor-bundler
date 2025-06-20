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

use EliasHaeussler\Typo3VendorBundler\Bundler;
use EliasHaeussler\Typo3VendorBundler\Config;
use EliasHaeussler\Typo3VendorBundler\Exception;
use Symfony\Component\Console;
use Throwable;

use function getcwd;
use function sprintf;
use function trim;

/**
 * BundleAutoloadCommand.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final class BundleAutoloadCommand extends AbstractConfigurationAwareCommand
{
    public function __construct()
    {
        parent::__construct('bundle-autoload');
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Bundle autoloader for vendor libraries in ext_emconf.php');

        $this->addArgument(
            'libs-dir',
            Console\Input\InputArgument::OPTIONAL,
            'Path to vendor libraries (either absolute or relative to working directory)',
        );

        $this->addOption(
            'drop-composer-autoload',
            'a',
            Console\Input\InputOption::VALUE_NONE | Console\Input\InputOption::VALUE_NEGATABLE,
            'Drop "autoload" section in composer.json',
        );
        $this->addOption(
            'target-file',
            't',
            Console\Input\InputOption::VALUE_REQUIRED,
            'File where to dump the generated classmap',
        );
        $this->addOption(
            'backup-sources',
            'b',
            Console\Input\InputOption::VALUE_NONE | Console\Input\InputOption::VALUE_NEGATABLE,
            'Backup source files before they get overwritten',
        );
        $this->addOption(
            'overwrite',
            'o',
            Console\Input\InputOption::VALUE_NONE | Console\Input\InputOption::VALUE_NEGATABLE,
            'Force overwriting the given target file, if it already exists',
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
        $config = $this->readConfigFile($input->getOption('config'), $rootPath);

        // Exit if config cannot be read
        if (null === $config) {
            return self::INVALID;
        }

        $rootPath = $config->rootPath() ?? $rootPath;
        $libsDir = $input->getArgument('libs-dir') ?? $config->pathToVendorLibraries();
        $dropComposerAutoload = $input->getOption('drop-composer-autoload') ?? $config->autoload()->dropComposerAutoload();
        $targetFile = $input->getOption('target-file') ?? $config->autoload()->targetFile();
        $backupSources = $input->getOption('backup-sources') ?? $config->autoload()->backupSources();
        $overwrite = $input->getOption('overwrite') ?? $config->autoload()->overwriteExistingTargetFile();
        $excludeFromClassMap = $config->autoload()->excludeFromClassMap();

        // Exit if libs directory is invalid
        if ('' === trim($libsDir)) {
            $this->io->error('Please provide a valid path to vendor libraries.');

            return self::INVALID;
        }

        $autoloadBundler = new Bundler\AutoloadBundler($rootPath, $libsDir, $this->io);

        try {
            $autoload = $autoloadBundler->bundle(
                $targetFile,
                $dropComposerAutoload,
                $backupSources,
                $overwrite,
                $excludeFromClassMap,
            );
        } catch (Exception\FileAlreadyExists $exception) {
            if (!$this->io->confirm('Target file already exists. Overwrite file?', false)) {
                throw $exception;
            }

            $autoload = $autoloadBundler->bundle(
                $targetFile,
                $dropComposerAutoload,
                $backupSources,
                true,
                $excludeFromClassMap,
            );
        }

        $this->io->success(
            sprintf('Successfully bundled autoload configurations in "%s".', $autoload->filename(true)),
        );

        return self::SUCCESS;
    }
}
