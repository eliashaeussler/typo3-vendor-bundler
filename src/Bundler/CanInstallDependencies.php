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

namespace EliasHaeussler\Typo3VendorBundler\Bundler;

use Composer\Composer;
use Composer\Factory;
use Composer\Installer;
use Composer\IO;
use EliasHaeussler\TaskRunner;
use EliasHaeussler\Typo3VendorBundler\Exception;
use EliasHaeussler\Typo3VendorBundler\Helper;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;
use Throwable;

/**
 * CanInstallDependencies.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
trait CanInstallDependencies
{
    /**
     * @throws Exception\CannotInstallComposerDependencies
     * @throws Throwable
     */
    private function installVendorLibraries(bool $includeDevDependencies = false): Composer
    {
        return $this->taskRunner->run(
            '📦 Installing vendor libraries',
            function (TaskRunner\RunnerContext $context) use ($includeDevDependencies) {
                $output = $context->output;
                $composer = $this->buildComposerInstance($this->librariesPath);
                $io = new IO\BufferIO('', $output->getVerbosity(), $output->getFormatter());

                try {
                    $installResult = Helper\FilesystemHelper::executeInDirectory(
                        $this->librariesPath,
                        static fn () => Installer::create($io, $composer)->setDevMode($includeDevDependencies)->run(),
                    );
                } catch (Exception\CannotDetectWorkingDirectory|Exception\DirectoryDoesNotExist $exception) {
                    throw new Exception\CannotInstallComposerDependencies($this->librariesPath, $exception);
                }

                if (Console\Command\Command::SUCCESS !== $installResult) {
                    $output->writeln($io->getOutput());

                    throw new Exception\CannotInstallComposerDependencies($this->librariesPath);
                }

                return $composer;
            },
        );
    }

    /**
     * @throws Exception\DeclarationFileIsInvalid
     */
    private function buildComposerInstance(string $basePath): Composer
    {
        $configFile = Filesystem\Path::join($basePath, 'composer.json');

        try {
            return Factory::create(new IO\NullIO(), $configFile);
        } catch (Throwable $exception) {
            throw new Exception\DeclarationFileIsInvalid($configFile, previous: $exception);
        }
    }
}
