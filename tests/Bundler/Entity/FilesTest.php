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
 * FilesTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Bundler\Entity\Files::class)]
final class FilesTest extends Framework\TestCase
{
    private Src\Bundler\Entity\Files $subject;

    public function setUp(): void
    {
        $this->subject = new Src\Bundler\Entity\Files(
            [
                'foo.php',
                'foo/baz.php',
            ],
            'files.php',
            __DIR__,
        );
    }

    #[Framework\Attributes\Test]
    public function hasReturnsTrueIfGivenRelativeFilePathExists(): void
    {
        self::assertTrue($this->subject->has('foo.php'));
        self::assertTrue($this->subject->has('foo/baz.php'));
        self::assertFalse($this->subject->has('baz.php'));
    }

    #[Framework\Attributes\Test]
    public function hasReturnsTrueIfGivenAbsoluteFilePathExists(): void
    {
        self::assertTrue($this->subject->has(__DIR__.'/foo.php'));
        self::assertTrue($this->subject->has(__DIR__.'/foo/baz.php'));
        self::assertFalse($this->subject->has(__DIR__.'/baz.php'));
    }

    #[Framework\Attributes\Test]
    public function removeDoesNothingIfGivenFilePathDoesNotExist(): void
    {
        $expected = $this->subject->toArray();

        $this->subject->remove('baz.php');

        self::assertSame($expected, $this->subject->toArray());
    }

    #[Framework\Attributes\Test]
    public function removeAppliesNewFilesObjectWithoutGivenFilePath(): void
    {
        $expected = [
            __DIR__.'/foo/baz.php',
        ];

        $this->subject->remove('foo.php');

        self::assertEquals($expected, $this->subject->toArray());
    }

    #[Framework\Attributes\Test]
    public function toArrayReturnsFiles(): void
    {
        $expected = [
            __DIR__.'/foo.php',
            __DIR__.'/foo/baz.php',
        ];

        self::assertSame($expected, $this->subject->toArray());
    }

    #[Framework\Attributes\Test]
    public function toArrayReturnsFilesWithRelativeFilePaths(): void
    {
        $expected = [
            'foo.php',
            'foo/baz.php',
        ];

        self::assertSame($expected, $this->subject->toArray(true));
    }

    #[Framework\Attributes\Test]
    public function filenameReturnsFilename(): void
    {
        self::assertSame(__DIR__.'/files.php', $this->subject->filename());
    }

    #[Framework\Attributes\Test]
    public function filenameReturnsFilenameAsRelativePath(): void
    {
        self::assertSame('files.php', $this->subject->filename(true));
    }

    #[Framework\Attributes\Test]
    public function mergeMergesFiles(): void
    {
        $other = new Src\Bundler\Entity\Files(
            [
                'baz.php',
                'baz/foo.php',
            ],
            'other-files.php',
            dirname(__DIR__),
        );

        $expected = new Src\Bundler\Entity\Files(
            [
                __DIR__.'/foo.php',
                __DIR__.'/foo/baz.php',
                dirname(__DIR__).'/baz.php',
                dirname(__DIR__).'/baz/foo.php',
            ],
            'files.php',
            __DIR__,
        );

        self::assertEquals($expected, $this->subject->merge($other));
    }

    #[Framework\Attributes\Test]
    public function mergeMergesFilesAndAppliesGivenFilename(): void
    {
        $other = new Src\Bundler\Entity\Files(
            [
                'baz.php',
                'baz/foo.php',
            ],
            'other-classmap.php',
            dirname(__DIR__),
        );

        $expected = new Src\Bundler\Entity\Files(
            [
                __DIR__.'/foo.php',
                __DIR__.'/foo/baz.php',
                dirname(__DIR__).'/baz.php',
                dirname(__DIR__).'/baz/foo.php',
            ],
            'merged-files.php',
            __DIR__,
        );

        self::assertEquals($expected, $this->subject->merge($other, 'merged-files.php'));
    }
}
