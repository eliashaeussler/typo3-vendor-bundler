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

use function array_diff;
use function array_map;
use function array_merge;
use function array_values;
use function in_array;

/**
 * ClassMap.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 *
 * @see https://getcomposer.org/doc/04-schema.md#classmap
 * @see https://docs.typo3.org/permalink/t3coreapi:confval-ext-emconf-autoload
 */
final readonly class ClassMap extends PathAwareBundle
{
    private string $filename;

    /**
     * @var list<string>
     */
    private array $map;

    /**
     * @param list<string> $map
     */
    public function __construct(
        array $map,
        string $filename,
        string $rootPath,
    ) {
        parent::__construct($rootPath);

        $this->map = array_map(
            $this->convertToAbsolutePath(...),
            $map,
        );
        $this->filename = $this->convertToAbsolutePath($filename);
    }

    public function has(string $path): bool
    {
        $fullPath = $this->convertToAbsolutePath($path);

        return in_array($fullPath, $this->map, true);
    }

    public function remove(string $path): self
    {
        $fullPath = $this->convertToAbsolutePath($path);

        if (!$this->has($fullPath)) {
            return $this;
        }

        return new self(
            array_values(array_diff($this->map, [$fullPath])),
            $this->filename,
            $this->rootPath,
        );
    }

    /**
     * @return list<string>
     */
    public function toArray(bool $useRelativePaths = false): array
    {
        if (!$useRelativePaths) {
            return $this->map;
        }

        return array_map(
            $this->convertToRelativePath(...),
            $this->map,
        );
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
        $map = array_merge($this->map, $other->map);

        return new self($map, $filename ?? $this->filename, $this->rootPath);
    }
}
