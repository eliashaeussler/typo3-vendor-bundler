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

use function array_pop;
use function dirname;
use function explode;
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
            throw new Exception\DeclarationFileIsInvalid($composerJson, $exception);
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
        $rootPath = $this->rootPath();
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

    public function readExtra(string $path): mixed
    {
        $extra = $this->composer->getPackage()->getExtra();
        $currentSegment = &$extra;
        $pathSegments = explode('.', $path);
        $lastSegment = array_pop($pathSegments);

        foreach ($pathSegments as $pathSegment) {
            $currentSegment[$pathSegment] ??= null;

            // Early return on non-array segments
            if (!is_array($currentSegment[$pathSegment])) {
                return null;
            }

            $currentSegment = &$currentSegment[$pathSegment];
        }

        return $currentSegment[$lastSegment] ?? null;
    }

    public function writeExtra(string $path, string $value): void
    {
        $extra = $this->composer->getPackage()->getExtra();
        $currentSegment = &$extra;
        $pathSegments = explode('.', $path);
        $lastSegment = array_pop($pathSegments);

        foreach ($pathSegments as $pathSegment) {
            $currentSegment[$pathSegment] ??= [];

            // Make sure segments are arrays
            if (!is_array($currentSegment[$pathSegment])) {
                $currentSegment[$pathSegment] = [];
            }

            $currentSegment = &$currentSegment[$pathSegment];
        }

        $currentSegment[$lastSegment] = $value;

        /* @phpstan-ignore argument.type */
        $this->composer->getConfig()->getConfigSource()->addProperty('extra', $extra);
        $this->composer->getPackage()->setExtra($extra);
    }

    public function declarationFile(): string
    {
        return $this->composer->getConfig()->getConfigSource()->getName();
    }

    public function rootPath(): string
    {
        return dirname($this->declarationFile());
    }
}
