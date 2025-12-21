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

use Symfony\Component\Console;
use Symfony\Component\Filesystem;

use function sprintf;

/**
 * ValidateBundlerConfigCommand.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final class ValidateBundlerConfigCommand extends AbstractConfigurationAwareCommand
{
    public function __construct()
    {
        parent::__construct('validate-bundler-config');
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Validate a given typo3-vendor-bundler configuration file');
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): int
    {
        $configFile = $input->getOption('config');
        $autoDetected = null === $configFile;
        $rootPath = (string) getcwd();
        $config = $this->readConfigFile($configFile, $rootPath);

        // Fail if config file is invalid
        if (null === $config) {
            return self::INVALID;
        }

        // Fail if no config file could be detected
        if (null === $configFile) {
            $this->io->error('No config file could be detected.');
            $this->io->writeln(
                '💡 You can pass the path to your config file using the <comment>--config</comment> option.',
            );

            return self::INVALID;
        }

        $messages = [
            sprintf('✅ Found config file: <info>%s</info>', Filesystem\Path::makeRelative($configFile, $rootPath)),
            '✅ Config file contains no invalid options.',
        ];

        if ($autoDetected) {
            $messages[0] .= ' <comment>(auto-detected)</comment>';
        }

        $this->io->writeln($messages, Console\Output\OutputInterface::VERBOSITY_VERBOSE);
        $this->io->success('Congratulations, your config file is valid.');

        return self::SUCCESS;
    }
}
