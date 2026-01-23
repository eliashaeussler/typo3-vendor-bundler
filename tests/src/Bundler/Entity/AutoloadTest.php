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
 * AutoloadTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Bundler\Entity\Autoload::class)]
final class AutoloadTest extends Framework\TestCase
{
    private Src\Bundler\Entity\Autoload $subject;

    public function setUp(): void
    {
        $classMap = new Src\Bundler\Entity\ClassMap(
            [
                'foo',
                'foo/baz',
            ],
            'classmap.php',
            __DIR__,
        );
        $psr4Namespaces = new Src\Bundler\Entity\Psr4Namespaces(
            [
                'Foo\\' => ['src'],
                'Baz\\' => ['other'],
            ],
            'namespaces.php',
            __DIR__,
        );
        $files = new Src\Bundler\Entity\Files(
            [
                'foo.php',
                'foo/baz.php',
            ],
            'files.php',
            __DIR__,
        );

        $this->subject = new Src\Bundler\Entity\Autoload(
            $classMap,
            $psr4Namespaces,
            $files,
            'merged.php',
            __DIR__,
        );
    }

    #[Framework\Attributes\Test]
    public function toArrayReturnsAutoloadConfiguration(): void
    {
        $expected = [
            'classmap' => [
                __DIR__.'/foo',
                __DIR__.'/foo/baz',
            ],
            'psr-4' => [
                'Foo\\' => [__DIR__.'/src'],
                'Baz\\' => [__DIR__.'/other'],
            ],
            'files' => [
                __DIR__.'/foo.php',
                __DIR__.'/foo/baz.php',
            ],
        ];

        self::assertSame($expected, $this->subject->toArray());
    }

    #[Framework\Attributes\Test]
    public function toArrayReturnsAutoloadConfigurationWithRelativePaths(): void
    {
        $expected = [
            'classmap' => [
                'foo',
                'foo/baz',
            ],
            'psr-4' => [
                'Foo\\' => ['src'],
                'Baz\\' => ['other'],
            ],
            'files' => [
                'foo.php',
                'foo/baz.php',
            ],
        ];

        self::assertSame($expected, $this->subject->toArray(true));
    }

    #[Framework\Attributes\Test]
    public function filenameReturnsFilename(): void
    {
        self::assertSame(__DIR__.'/merged.php', $this->subject->filename());
    }

    #[Framework\Attributes\Test]
    public function filenameReturnsFilenameAsRelativePath(): void
    {
        self::assertSame('merged.php', $this->subject->filename(true));
    }

    #[Framework\Attributes\Test]
    public function mergeMergesClassMaps(): void
    {
        $other = new Src\Bundler\Entity\Autoload(
            new Src\Bundler\Entity\ClassMap(
                [
                    'baz',
                    'baz/foo',
                ],
                'other-classmap.php',
                dirname(__DIR__),
            ),
            $this->subject->psr4Namespaces,
            $this->subject->files,
            'other.json',
            dirname(__DIR__),
        );

        $expected = new Src\Bundler\Entity\Autoload(
            new Src\Bundler\Entity\ClassMap(
                [
                    __DIR__.'/foo',
                    __DIR__.'/foo/baz',
                    dirname(__DIR__).'/baz',
                    dirname(__DIR__).'/baz/foo',
                ],
                'classmap.php',
                __DIR__,
            ),
            new Src\Bundler\Entity\Psr4Namespaces(
                [
                    'Foo\\' => ['src'],
                    'Baz\\' => ['other'],
                ],
                'namespaces.php',
                __DIR__,
            ),
            new Src\Bundler\Entity\Files(
                [
                    'foo.php',
                    'foo/baz.php',
                ],
                'files.php',
                __DIR__,
            ),
            'merged.php',
            __DIR__,
        );

        self::assertEquals($expected, $this->subject->merge($other));
    }

    #[Framework\Attributes\Test]
    public function mergeMergesPsr4Namespaces(): void
    {
        $other = new Src\Bundler\Entity\Autoload(
            $this->subject->classMap,
            new Src\Bundler\Entity\Psr4Namespaces(
                [
                    'Boo\\' => ['boo'],
                ],
                'other-namespaces.php',
                dirname(__DIR__),
            ),
            $this->subject->files,
            'other.json',
            dirname(__DIR__),
        );

        $expected = new Src\Bundler\Entity\Autoload(
            new Src\Bundler\Entity\ClassMap(
                [
                    __DIR__.'/foo',
                    __DIR__.'/foo/baz',
                ],
                'classmap.php',
                __DIR__,
            ),
            new Src\Bundler\Entity\Psr4Namespaces(
                [
                    'Foo\\' => [__DIR__.'/src'],
                    'Baz\\' => [__DIR__.'/other'],
                    'Boo\\' => [dirname(__DIR__).'/boo'],
                ],
                'namespaces.php',
                __DIR__,
            ),
            new Src\Bundler\Entity\Files(
                [
                    'foo.php',
                    'foo/baz.php',
                ],
                'files.php',
                __DIR__,
            ),
            'merged.php',
            __DIR__,
        );

        self::assertEquals($expected, $this->subject->merge($other));
    }

    #[Framework\Attributes\Test]
    public function mergeMergesFiles(): void
    {
        $other = new Src\Bundler\Entity\Autoload(
            $this->subject->classMap,
            $this->subject->psr4Namespaces,
            new Src\Bundler\Entity\Files(
                [
                    'boo.php',
                ],
                'other-files.php',
                dirname(__DIR__),
            ),
            'other.json',
            dirname(__DIR__),
        );

        $expected = new Src\Bundler\Entity\Autoload(
            new Src\Bundler\Entity\ClassMap(
                [
                    __DIR__.'/foo',
                    __DIR__.'/foo/baz',
                ],
                'classmap.php',
                __DIR__,
            ),
            new Src\Bundler\Entity\Psr4Namespaces(
                [
                    'Foo\\' => [__DIR__.'/src'],
                    'Baz\\' => [__DIR__.'/other'],
                ],
                'namespaces.php',
                __DIR__,
            ),
            new Src\Bundler\Entity\Files(
                [
                    __DIR__.'/foo.php',
                    __DIR__.'/foo/baz.php',
                    dirname(__DIR__).'/boo.php',
                ],
                'files.php',
                __DIR__,
            ),
            'merged.php',
            __DIR__,
        );

        self::assertEquals($expected, $this->subject->merge($other));
    }

    #[Framework\Attributes\Test]
    public function mergeMergesAutoloadsAndAppliesGivenFilename(): void
    {
        $other = new Src\Bundler\Entity\Autoload(
            new Src\Bundler\Entity\ClassMap(
                [
                    'baz',
                    'baz/foo',
                ],
                'other-classmap.php',
                dirname(__DIR__),
            ),
            new Src\Bundler\Entity\Psr4Namespaces(
                [
                    'Boo\\' => ['boo'],
                ],
                'other-namespaces.php',
                dirname(__DIR__),
            ),
            new Src\Bundler\Entity\Files(
                [
                    'boo.php',
                ],
                'other-files.php',
                dirname(__DIR__),
            ),
            'other.json',
            dirname(__DIR__),
        );

        $expected = new Src\Bundler\Entity\Autoload(
            new Src\Bundler\Entity\ClassMap(
                [
                    __DIR__.'/foo',
                    __DIR__.'/foo/baz',
                    dirname(__DIR__).'/baz',
                    dirname(__DIR__).'/baz/foo',
                ],
                'merged-autoloads.php',
                __DIR__,
            ),
            new Src\Bundler\Entity\Psr4Namespaces(
                [
                    'Foo\\' => [__DIR__.'/src'],
                    'Baz\\' => [__DIR__.'/other'],
                    'Boo\\' => [dirname(__DIR__).'/boo'],
                ],
                'merged-autoloads.php',
                __DIR__,
            ),
            new Src\Bundler\Entity\Files(
                [
                    __DIR__.'/foo.php',
                    __DIR__.'/foo/baz.php',
                    dirname(__DIR__).'/boo.php',
                ],
                'merged-autoloads.php',
                __DIR__,
            ),
            'merged-autoloads.php',
            __DIR__,
        );

        self::assertEquals($expected, $this->subject->merge($other, 'merged-autoloads.php'));
    }
}
