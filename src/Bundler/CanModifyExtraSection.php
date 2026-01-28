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

namespace EliasHaeussler\Typo3VendorBundler\Bundler;

use Symfony\Component\Filesystem;

/**
 * CanModifyExtraSection.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
trait CanModifyExtraSection
{
    private function modifyExtraSection(string $key, string $value): void
    {
        $this->rootComposer->writeExtra('typo3/cms.vendor-libraries.'.$key, $value);
    }

    private function extraSectionNeedsUpdate(string $key, string $value): bool
    {
        $configuredValue = $this->rootComposer->readExtra('typo3/cms.vendor-libraries.'.$key);

        return $configuredValue !== $value;
    }

    private function prepareExtraSection(): void
    {
        $this->modifyExtraSection(
            'root-path',
            Filesystem\Path::makeRelative($this->librariesPath, $this->rootPath),
        );
    }

    private function extraSectionIsPrepared(): bool
    {
        return !$this->extraSectionNeedsUpdate(
            'root-path',
            Filesystem\Path::makeRelative($this->librariesPath, $this->rootPath),
        );
    }
}
