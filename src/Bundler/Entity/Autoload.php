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

namespace EliasHaeussler\Typo3VendorBundler\Bundler\Entity;

use Symfony\Component\Filesystem;

/**
 * Autoload.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final readonly class Autoload implements Bundle
{
    private string $filename;

    public function __construct(
        private ClassMap $classMap,
        private Psr4Namespaces $psr4Namespaces,
        string $filename,
        private string $rootPath,
    ) {
        $this->filename = Filesystem\Path::makeAbsolute($filename, $this->rootPath);
    }

    /**
     * @return array{
     *     classmap: list<string>,
     *     psr-4: array<string, string>,
     * }
     */
    public function toArray(bool $useRelativePaths = false): array
    {
        return [
            'classmap' => $this->classMap->toArray($useRelativePaths),
            'psr-4' => $this->psr4Namespaces->toArray($useRelativePaths),
        ];
    }

    public function filename(bool $asRelativePath = false): string
    {
        if ($asRelativePath) {
            return Filesystem\Path::makeRelative($this->filename, $this->rootPath);
        }

        return $this->filename;
    }
}
