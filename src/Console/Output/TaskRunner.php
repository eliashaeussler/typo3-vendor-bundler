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

namespace EliasHaeussler\Typo3VendorBundler\Console\Output;

use Closure;
use Symfony\Component\Console;
use Throwable;

/**
 * TaskRunner.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final readonly class TaskRunner
{
    public function __construct(
        private Console\Output\OutputInterface $output,
    ) {}

    /**
     * @template T
     *
     * @param Closure(bool): T                            $task
     * @param Console\Output\OutputInterface::VERBOSITY_* $verbosity
     *
     * @return T
     */
    public function run(
        string $message,
        Closure $task,
        int $verbosity = Console\Output\OutputInterface::VERBOSITY_NORMAL,
    ): mixed {
        $successful = true;

        $this->output->write($message.'... ', false, $verbosity);

        try {
            $result = $task($successful);

            /* @phpstan-ignore if.alwaysTrue */
            if ($successful) {
                $this->output->writeln('<info>Done</info>', $verbosity);
            } else {
                $this->output->writeln('<error>Failed</error>', $verbosity);
            }

            return $result;
        } catch (Throwable $exception) {
            $this->output->writeln('<error>Failed</error>', $verbosity);

            throw $exception;
        }
    }
}
