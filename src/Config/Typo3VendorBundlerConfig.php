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

namespace EliasHaeussler\Typo3VendorBundler\Config;

/**
 * Typo3VendorBundlerConfig.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final class Typo3VendorBundlerConfig
{
    public function __construct(
        private readonly AutoloadConfig $autoload = new AutoloadConfig(),
        private readonly DependenciesConfig $dependencies = new DependenciesConfig(),
        private readonly DependencyExtractionConfig $dependencyExtraction = new DependencyExtractionConfig(),
        private readonly string $pathToVendorLibraries = 'Resources/Private/Libs',
        private ?string $rootPath = null,
    ) {}

    public function autoload(): AutoloadConfig
    {
        return $this->autoload;
    }

    public function dependencies(): DependenciesConfig
    {
        return $this->dependencies;
    }

    public function dependencyExtraction(): DependencyExtractionConfig
    {
        return $this->dependencyExtraction;
    }

    public function pathToVendorLibraries(): string
    {
        return $this->pathToVendorLibraries;
    }

    public function rootPath(): ?string
    {
        return $this->rootPath;
    }

    public function setRootPath(string $rootPath): self
    {
        $this->rootPath = $rootPath;

        return $this;
    }
}
