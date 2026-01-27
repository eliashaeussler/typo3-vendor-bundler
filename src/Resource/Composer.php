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

namespace EliasHaeussler\Typo3VendorBundler\Resource;

use Composer\Factory;
use Composer\Installer;
use Composer\IO;
use EliasHaeussler\Typo3VendorBundler\Exception;
use EliasHaeussler\Typo3VendorBundler\Helper;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;
use Throwable;

use function dirname;
use function is_dir;

/**
 * Composer.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final readonly class Composer
{
    public function __construct(
        public \Composer\Composer $composer,
    ) {}

    /**
     * @throws Exception\DeclarationFileIsInvalid
     */
    public static function create(string $composerJson): self
    {
        if (is_dir($composerJson)) {
            $composerJson = Filesystem\Path::join($composerJson, 'composer.json');
        }

        try {
            $composer = Factory::create(new IO\NullIO(), $composerJson);
        } catch (Throwable $exception) {
            throw new Exception\DeclarationFileIsInvalid($composerJson, previous: $exception);
        }

        return new self($composer);
    }

    /**
     * @throws Exception\CannotInstallComposerDependencies
     */
    public function install(
        bool $includeDevDependencies = true,
        Console\Output\OutputInterface $output = new Console\Output\NullOutput(),
    ): void {
        $rootPath = dirname($this->composer->getConfig()->getConfigSource()->getName());
        $io = new IO\BufferIO('', $output->getVerbosity(), $output->getFormatter());

        try {
            $installResult = Helper\FilesystemHelper::executeInDirectory(
                $rootPath,
                fn () => Installer::create($io, $this->composer)->setDevMode($includeDevDependencies)->run(),
            );
        } catch (Exception\CannotDetectWorkingDirectory|Exception\DirectoryDoesNotExist $exception) {
            throw new Exception\CannotInstallComposerDependencies($rootPath, $exception);
        }

        if (Console\Command\Command::SUCCESS !== $installResult) {
            $output->writeln($io->getOutput());

            throw new Exception\CannotInstallComposerDependencies($rootPath);
        }
    }
}
