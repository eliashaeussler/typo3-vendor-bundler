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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Bundler\Entity;

use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;

use function dirname;

/**
 * Psr4NamespacesTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Bundler\Entity\Psr4Namespaces::class)]
final class Psr4NamespacesTest extends Framework\TestCase
{
    private Src\Bundler\Entity\Psr4Namespaces $subject;

    public function setUp(): void
    {
        $this->subject = new Src\Bundler\Entity\Psr4Namespaces(
            [
                'Foo\\' => ['src'],
                'Baz\\' => ['other'],
            ],
            'namespaces.php',
            __DIR__,
        );
    }

    #[Framework\Attributes\Test]
    public function toArrayReturnsPsr4Namespaces(): void
    {
        $expected = [
            'Foo\\' => [__DIR__.'/src'],
            'Baz\\' => [__DIR__.'/other'],
        ];

        self::assertSame($expected, $this->subject->toArray());
    }

    #[Framework\Attributes\Test]
    public function toArrayReturnsPsr4NamespacesWithRelativePaths(): void
    {
        $expected = [
            'Foo\\' => ['src'],
            'Baz\\' => ['other'],
        ];

        self::assertSame($expected, $this->subject->toArray(true));
    }

    #[Framework\Attributes\Test]
    public function filenameReturnsFilename(): void
    {
        self::assertSame(__DIR__.'/namespaces.php', $this->subject->filename());
    }

    #[Framework\Attributes\Test]
    public function filenameReturnsFilenameAsRelativePath(): void
    {
        self::assertSame('namespaces.php', $this->subject->filename(true));
    }

    #[Framework\Attributes\Test]
    public function mergeMergesPsr4Namespaces(): void
    {
        $other = new Src\Bundler\Entity\Psr4Namespaces(
            [
                'Boo\\' => ['boo'],
            ],
            'other-namespaces.php',
            dirname(__DIR__),
        );

        $expected = new Src\Bundler\Entity\Psr4Namespaces(
            [
                'Foo\\' => [__DIR__.'/src'],
                'Baz\\' => [__DIR__.'/other'],
                'Boo\\' => [dirname(__DIR__).'/boo'],
            ],
            'namespaces.php',
            __DIR__,
        );

        self::assertEquals($expected, $this->subject->merge($other));
    }

    #[Framework\Attributes\Test]
    public function mergeMergesPsr4NamespacessAndAppliesGivenFilename(): void
    {
        $other = new Src\Bundler\Entity\Psr4Namespaces(
            [
                'Boo\\' => ['boo'],
            ],
            'other-namespaces.php',
            dirname(__DIR__),
        );

        $expected = new Src\Bundler\Entity\Psr4Namespaces(
            [
                'Foo\\' => [__DIR__.'/src'],
                'Baz\\' => [__DIR__.'/other'],
                'Boo\\' => [dirname(__DIR__).'/boo'],
            ],
            'merged-namespaces.php',
            __DIR__,
        );

        self::assertEquals($expected, $this->subject->merge($other, 'merged-namespaces.php'));
    }
}
