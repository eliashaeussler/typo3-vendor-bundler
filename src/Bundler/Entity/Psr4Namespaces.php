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

namespace EliasHaeussler\Typo3VendorBundler\Bundler\Entity;

use Symfony\Component\Filesystem;

use function array_map;
use function in_array;

/**
 * Psr4Namespaces.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 *
 * @see https://getcomposer.org/doc/04-schema.md#psr-4
 * @see https://docs.typo3.org/permalink/t3coreapi:confval-ext-emconf-autoload
 */
final class Psr4Namespaces extends PathAwareBundle
{
    private readonly string $filename;

    /**
     * @var array<string, array<string>>
     */
    private readonly array $namespaces;

    /**
     * @param array<string, array<string>> $namespaces
     */
    public function __construct(array $namespaces, string $filename, string $rootPath)
    {
        parent::__construct($rootPath);

        foreach ($namespaces as $prefix => $paths) {
            $namespaces[$prefix] = array_map($this->convertToAbsolutePath(...), $paths);
        }

        $this->namespaces = $namespaces;
        $this->filename = $this->convertToAbsolutePath($filename);
    }

    /**
     * @return array<string, array<string>>
     */
    public function toArray(bool $useRelativePaths = false): array
    {
        if (!$useRelativePaths) {
            return $this->namespaces;
        }

        $namespaces = [];

        foreach ($this->namespaces as $prefix => $paths) {
            $namespaces[$prefix] = array_map($this->convertToRelativePath(...), $paths);
        }

        return $namespaces;
    }

    public function filename(bool $asRelativePath = false): string
    {
        if ($asRelativePath) {
            return Filesystem\Path::makeRelative($this->filename, $this->rootPath);
        }

        return $this->filename;
    }

    public function merge(self $other, ?string $filename = null): self
    {
        $theseNamespaces = $this->namespaces;
        $otherNamespaces = $other->namespaces;

        foreach ($otherNamespaces as $prefix => $paths) {
            $theseNamespaces[$prefix] ??= [];

            foreach ($paths as $path) {
                if (!in_array($path, $theseNamespaces[$prefix], true)) {
                    $theseNamespaces[$prefix][] = $path;
                }
            }
        }

        return new self($theseNamespaces, $filename ?? $this->filename, $this->rootPath);
    }
}
