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
use function array_merge;
use function array_unique;
use function array_values;

/**
 * FilesBundle.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 *
 * @see https://getcomposer.org/doc/04-schema.md#files
 */
final class Files extends PathAwareBundle implements MergeableBundle
{
    private readonly string $filename;

    /**
     * @var list<string>
     */
    private array $files;

    /**
     * @param list<string> $files
     */
    public function __construct(array $files, string $filename, string $rootPath)
    {
        parent::__construct($rootPath);

        $this->files = array_map($this->convertToAbsolutePath(...), $files);
        $this->filename = $this->convertToAbsolutePath($filename);
    }

    public function has(string $path): bool
    {
        $fullPath = $this->convertToAbsolutePath($path);

        return in_array($fullPath, $this->files, true);
    }

    public function remove(string $path): void
    {
        $fullPath = $this->convertToAbsolutePath($path);

        if ($this->has($fullPath)) {
            $this->files = array_values(array_diff($this->files, [$fullPath]));
        }
    }

    /**
     * @return list<string>
     */
    public function toArray(bool $useRelativePaths = false): array
    {
        if (!$useRelativePaths) {
            return $this->files;
        }

        return array_map(
            $this->convertToRelativePath(...),
            $this->files,
        );
    }

    public function filename(bool $asRelativePath = false): string
    {
        if ($asRelativePath) {
            return Filesystem\Path::makeRelative($this->filename, $this->rootPath);
        }

        return $this->filename;
    }

    public function merge(MergeableBundle $other, ?string $filename = null): static
    {
        $files = array_values(
            array_unique(
                array_merge($this->files, $other->files),
            ),
        );

        return new self($files, $filename ?? $this->filename, $this->rootPath);
    }
}
