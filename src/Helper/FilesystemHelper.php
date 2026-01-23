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

namespace EliasHaeussler\Typo3VendorBundler\Helper;

use Closure;
use EliasHaeussler\Typo3VendorBundler\Exception;

use function chdir;
use function getcwd;
use function is_dir;

/**
 * FilesystemHelper.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final class FilesystemHelper
{
    /**
     * @template T
     *
     * @param Closure(): T $function
     *
     * @return T
     *
     * @throws Exception\CannotDetectWorkingDirectory
     * @throws Exception\DirectoryDoesNotExist
     */
    public static function executeInDirectory(string $directory, Closure $function): mixed
    {
        $workingDirectory = getcwd();

        // Fail if current working directory cannot be determined
        if (false === $workingDirectory) {
            throw new Exception\CannotDetectWorkingDirectory();
        }

        // Fail if directory does not exist
        if (!is_dir($directory)) {
            throw new Exception\DirectoryDoesNotExist($directory);
        }

        // Temporarily change working directory to given directory
        chdir($directory);

        try {
            return $function();
        } finally {
            // Change back to initial working directory
            chdir($workingDirectory);
        }
    }
}
