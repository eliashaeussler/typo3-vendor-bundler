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

/**
 * AutoloadTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Bundler\Entity\Autoload::class)]
final class AutoloadTest extends Framework\TestCase
{
    private Src\Bundler\Entity\ClassMap $classMap;
    private Src\Bundler\Entity\Psr4Namespaces $psr4Namespaces;
    private Src\Bundler\Entity\Autoload $subject;

    public function setUp(): void
    {
        $this->classMap = new Src\Bundler\Entity\ClassMap(
            ['foo'],
            'classmap.php',
            __DIR__,
        );
        $this->psr4Namespaces = new Src\Bundler\Entity\Psr4Namespaces(
            [
                'Foo\\' => 'src',
            ],
            'namespaces.php',
            __DIR__,
        );
        $this->subject = new Src\Bundler\Entity\Autoload(
            $this->classMap,
            $this->psr4Namespaces,
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
            ],
            'psr-4' => [
                'Foo\\' => __DIR__.'/src',
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
            ],
            'psr-4' => [
                'Foo\\' => 'src',
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
}
