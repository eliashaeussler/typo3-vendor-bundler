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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Bundler\Entity;

use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;

use function dirname;

/**
 * ClassMapTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Bundler\Entity\ClassMap::class)]
final class ClassMapTest extends Framework\TestCase
{
    private Src\Bundler\Entity\ClassMap $subject;

    public function setUp(): void
    {
        $this->subject = new Src\Bundler\Entity\ClassMap(
            [
                'foo',
                'foo/baz',
            ],
            'classmap.php',
            __DIR__,
        );
    }

    #[Framework\Attributes\Test]
    public function hasReturnsTrueIfGivenRelativePathExistsInMap(): void
    {
        self::assertTrue($this->subject->has('foo'));
        self::assertTrue($this->subject->has('foo/baz'));
        self::assertFalse($this->subject->has('baz'));
    }

    #[Framework\Attributes\Test]
    public function hasReturnsTrueIfGivenAbsolutePathExistsInMap(): void
    {
        self::assertTrue($this->subject->has(__DIR__.'/foo'));
        self::assertTrue($this->subject->has(__DIR__.'/foo/baz'));
        self::assertFalse($this->subject->has(__DIR__.'/baz'));
    }

    #[Framework\Attributes\Test]
    public function removeDoesNothingIfGivenPathDoesNotExistInMap(): void
    {
        $expected = $this->subject->toArray();

        self::assertSame($this->subject, $this->subject->remove('baz'));
        self::assertSame($expected, $this->subject->toArray());
    }

    #[Framework\Attributes\Test]
    public function removeReturnsNewClassMapObjectWithoutGivenPath(): void
    {
        $expected = new Src\Bundler\Entity\ClassMap(
            [
                'foo/baz',
            ],
            'classmap.php',
            __DIR__,
        );

        self::assertEquals($expected, $this->subject->remove('foo'));
    }

    #[Framework\Attributes\Test]
    public function toArrayReturnsClassMap(): void
    {
        $expected = [
            __DIR__.'/foo',
            __DIR__.'/foo/baz',
        ];

        self::assertSame($expected, $this->subject->toArray());
    }

    #[Framework\Attributes\Test]
    public function toArrayReturnsClassMapWithRelativePaths(): void
    {
        $expected = [
            'foo',
            'foo/baz',
        ];

        self::assertSame($expected, $this->subject->toArray(true));
    }

    #[Framework\Attributes\Test]
    public function filenameReturnsFilename(): void
    {
        self::assertSame(__DIR__.'/classmap.php', $this->subject->filename());
    }

    #[Framework\Attributes\Test]
    public function filenameReturnsFilenameAsRelativePath(): void
    {
        self::assertSame('classmap.php', $this->subject->filename(true));
    }

    #[Framework\Attributes\Test]
    public function mergeMergesClassMaps(): void
    {
        $other = new Src\Bundler\Entity\ClassMap(
            [
                'baz',
                'baz/foo',
            ],
            'other-classmap.php',
            dirname(__DIR__),
        );

        $expected = new Src\Bundler\Entity\ClassMap(
            [
                __DIR__.'/foo',
                __DIR__.'/foo/baz',
                dirname(__DIR__).'/baz',
                dirname(__DIR__).'/baz/foo',
            ],
            'classmap.php',
            __DIR__,
        );

        self::assertEquals($expected, $this->subject->merge($other));
    }

    #[Framework\Attributes\Test]
    public function mergeMergesClassMapsAndAppliesGivenFilename(): void
    {
        $other = new Src\Bundler\Entity\ClassMap(
            [
                'baz',
                'baz/foo',
            ],
            'other-classmap.php',
            dirname(__DIR__),
        );

        $expected = new Src\Bundler\Entity\ClassMap(
            [
                __DIR__.'/foo',
                __DIR__.'/foo/baz',
                dirname(__DIR__).'/baz',
                dirname(__DIR__).'/baz/foo',
            ],
            'merged-classmap.php',
            __DIR__,
        );

        self::assertEquals($expected, $this->subject->merge($other, 'merged-classmap.php'));
    }
}
