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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Fixtures\Classes;

use EliasHaeussler\Typo3VendorBundler\Command;
use EliasHaeussler\Typo3VendorBundler\Config;
use Symfony\Component\Console;

/**
 * DummyCommand.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
final class DummyCommand extends Command\AbstractConfigurationAwareCommand
{
    public function __construct(
        string $description = 'dummy',
        string $name = 'dummy',
        ?Console\Output\OutputInterface $output = null,
    ) {
        parent::__construct($name);

        $this->configReader = new Config\ConfigReader();
        $this->io = new Console\Style\SymfonyStyle(
            new Console\Input\StringInput(''),
            $output ?? new Console\Output\NullOutput(),
        );

        $this->setDescription($description);
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): int
    {
        return self::SUCCESS;
    }

    public function readConfigFile(?string &$configFile, string $rootPath): ?Config\Typo3VendorBundlerConfig
    {
        return parent::readConfigFile($configFile, $rootPath);
    }
}
