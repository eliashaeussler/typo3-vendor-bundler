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
 * PathAwareBundle.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
abstract readonly class PathAwareBundle implements Bundle
{
    protected Filesystem\Filesystem $filesystem;

    public function __construct(
        protected string $rootPath,
    ) {
        $this->filesystem = new Filesystem\Filesystem();
    }

    protected function convertToAbsolutePath(string $path): string
    {
        return Filesystem\Path::makeAbsolute($path, $this->rootPath);
    }

    protected function convertToRelativePath(string $path): string
    {
        return Filesystem\Path::makeRelative($path, $this->rootPath);
    }
}
