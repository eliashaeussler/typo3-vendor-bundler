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

use Composer\Command;
use CuyZ\Valinor;
use EliasHaeussler\Typo3VendorBundler\Config;
use EliasHaeussler\Typo3VendorBundler\Exception;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;

use function sprintf;

/**
 * AbstractConfigurationAwareCommand.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
abstract class AbstractConfigurationAwareCommand extends Command\BaseCommand
{
    protected Config\ConfigReader $configReader;
    protected Console\Style\SymfonyStyle $io;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);

        $this->configReader = new Config\ConfigReader();
    }

    protected function configure(): void
    {
        $this->addOption(
            'config',
            'c',
            Console\Input\InputOption::VALUE_REQUIRED,
            'Path to configuration file (JSON, YAML or PHP)',
        );
    }

    protected function initialize(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): void
    {
        $this->io = new Console\Style\SymfonyStyle($input, $output);
    }

    protected function readConfigFile(?string $configFile, string $rootPath): ?Config\Typo3VendorBundlerConfig
    {
        $configFile ??= $this->configReader->detectFile($rootPath);

        // Early return if no config file is configured or could be detected
        if (null === $configFile) {
            return new Config\Typo3VendorBundlerConfig(rootPath: $rootPath);
        }

        // Make sure we use absolute paths everywhere
        if (Filesystem\Path::isRelative($configFile)) {
            $configFile = Filesystem\Path::makeAbsolute($configFile, $rootPath);
        }

        try {
            return $this->configReader->readFromFile($configFile);
        } catch (Valinor\Mapper\MappingError $error) {
            $this->decorateMappingError($error, $configFile);
        } catch (Exception\Exception $exception) {
            $this->io->error($exception->getMessage());
        }

        return null;
    }

    protected function decorateMappingError(Valinor\Mapper\MappingError $error, string $configFile): void
    {
        $errorMessages = [];

        $this->io->error(
            sprintf('The config file "%s" is invalid.', $configFile),
        );

        foreach ($error->messages() as $propertyError) {
            $errorMessages[] = sprintf('%s: %s', $propertyError->path(), $propertyError->toString());
        }

        $this->io->listing($errorMessages);
    }
}
