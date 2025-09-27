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

use function sprintf;

/**
 * BundleCommand.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final class BundleCommand extends AbstractConfigurationAwareCommand
{
    /**
     * @param list<Console\Command\Command> $bundlers
     */
    public function __construct(
        private readonly array $bundlers = [
            new BundleAutoloadCommand(),
        ],
    ) {
        parent::__construct('bundle');
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Execute all available bundlers');
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): int
    {
        foreach ($this->bundlers as $bundler) {
            $result = $this->runBundler($bundler, $input, $output);

            if (self::SUCCESS !== $result) {
                return $result;
            }
        }

        return self::SUCCESS;
    }

    private function runBundler(
        Console\Command\Command $bundler,
        Console\Input\InputInterface $input,
        Console\Output\OutputInterface $output,
    ): int {
        $config = $input->getOption('config');
        $parameters = [];

        if (null !== $config && $bundler->getDefinition()->hasOption('config')) {
            $parameters = [
                '--config' => $config,
            ];
        }

        $this->io->title($bundler->getDescription());
        $this->io->writeln(
            /* @phpstan-ignore argument.type */
            sprintf('💡 Run manually with <fg=cyan>%s %s</>', $_SERVER['PHP_SELF'], (string) $bundler->getName()),
        );
        $this->io->newLine();

        return $bundler->run(
            new Console\Input\ArrayInput($parameters, $bundler->getDefinition()),
            $output,
        );
    }
}
