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

namespace EliasHaeussler\Typo3VendorBundler\Config;

use EliasHaeussler\Typo3VendorBundler\Bundler;

/**
 * AutoloadConfig.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final readonly class AutoloadConfig
{
    /**
     * @param list<non-empty-string> $excludeFromClassMap
     */
    public function __construct(
        private bool $dropComposerAutoload = true,
        private AutoloadTarget $target = new AutoloadTarget(),
        private bool $backupSources = false,
        private array $excludeFromClassMap = [],
    ) {}

    public function dropComposerAutoload(): bool
    {
        if (Bundler\Entity\Manifest::Composer === $this->target->manifest()) {
            return false;
        }

        return $this->dropComposerAutoload;
    }

    public function target(): AutoloadTarget
    {
        return $this->target;
    }

    public function backupSources(): bool
    {
        return $this->backupSources;
    }

    /**
     * @return list<non-empty-string>
     */
    public function excludeFromClassMap(): array
    {
        return $this->excludeFromClassMap;
    }
}
