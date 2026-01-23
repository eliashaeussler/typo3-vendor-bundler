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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Helper;

use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;

use function getcwd;

/**
 * FilesystemHelperTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Helper\FilesystemHelper::class)]
final class FilesystemHelperTest extends Framework\TestCase
{
    #[Framework\Attributes\Test]
    public function executeInDirectoryThrowsExceptionIfGivenDirectoryIsInvalid(): void
    {
        $this->expectExceptionObject(
            new Src\Exception\DirectoryDoesNotExist('/foo'),
        );

        Src\Helper\FilesystemHelper::executeInDirectory('/foo', static fn () => getcwd());
    }

    #[Framework\Attributes\Test]
    public function executeInDirectoryExecutesGivenFunctionInGivenDirectory(): void
    {
        self::assertSame(
            __DIR__,
            Src\Helper\FilesystemHelper::executeInDirectory(__DIR__, static fn () => getcwd()),
        );
    }

    #[Framework\Attributes\Test]
    public function executeInDirectorySwitchesBackToInitialWorkingDirectory(): void
    {
        $expected = getcwd();

        Src\Helper\FilesystemHelper::executeInDirectory(__DIR__, static fn () => getcwd());

        self::assertSame($expected, getcwd());
    }
}
