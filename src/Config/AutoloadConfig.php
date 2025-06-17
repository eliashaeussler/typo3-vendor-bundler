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

/**
 * AutoloadConfig.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final readonly class AutoloadConfig
{
    public function __construct(
        private bool $dropComposerAutoload = true,
        private string $targetFile = 'ext_emconf.php',
        private bool $backupSources = false,
    ) {}

    public function dropComposerAutoload(): bool
    {
        return $this->dropComposerAutoload;
    }

    public function targetFile(): string
    {
        return $this->targetFile;
    }

    public function backupSources(): bool
    {
        return $this->backupSources;
    }
}
