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

/**
 * Autoload.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final readonly class Autoload implements MergeableBundle
{
    public ClassMap $classMap;
    public Psr4Namespaces $psr4Namespaces;
    public Files $files;
    private string $filename;

    public function __construct(
        ?ClassMap $classMap,
        ?Psr4Namespaces $psr4Namespaces,
        ?Files $files,
        string $filename,
        public string $rootPath,
    ) {
        $this->classMap = $classMap ?? new ClassMap([], $filename, $rootPath);
        $this->psr4Namespaces = $psr4Namespaces ?? new Psr4Namespaces([], $filename, $rootPath);
        $this->files = $files ?? new Files([], $filename, $rootPath);
        $this->filename = Filesystem\Path::makeAbsolute($filename, $this->rootPath);
    }

    /**
     * @return array{
     *     classmap?: list<string>,
     *     psr-4?: array<string, array<string>>,
     *     files?: list<string>,
     * }
     */
    public function toArray(bool $useRelativePaths = false): array
    {
        $autoload = [];
        $classMap = $this->classMap->toArray($useRelativePaths);
        $psr4Namespaces = $this->psr4Namespaces->toArray($useRelativePaths);
        $files = $this->files->toArray($useRelativePaths);

        if ([] !== $classMap) {
            $autoload['classmap'] = $classMap;
        }
        if ([] !== $psr4Namespaces) {
            $autoload['psr-4'] = $psr4Namespaces;
        }
        if ([] !== $files) {
            $autoload['files'] = $files;
        }

        return $autoload;
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
        return new self(
            $this->classMap->merge($other->classMap, $filename),
            $this->psr4Namespaces->merge($other->psr4Namespaces, $filename),
            $this->files->merge($other->files, $filename),
            $filename ?? $this->filename,
            $this->rootPath,
        );
    }
}
