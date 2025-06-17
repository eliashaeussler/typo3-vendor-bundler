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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Resource;

use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;

use function dirname;

/**
 * ExtEmConfParserTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Resource\ExtEmConfParser::class)]
final class ExtEmConfParserTest extends Framework\TestCase
{
    private Src\Resource\ExtEmConfParser $subject;

    public function setUp(): void
    {
        $this->subject = new Src\Resource\ExtEmConfParser();
    }

    #[Framework\Attributes\Test]
    public function parseThrowsExceptionIfDeclarationFileDoesNotExist(): void
    {
        $this->expectExceptionObject(
            new Src\Exception\FileDoesNotExist('foo'),
        );

        $this->subject->parse('foo');
    }

    #[Framework\Attributes\Test]
    public function parseThrowsExceptionIfDeclarationFileDoesNotProvideExtensionConfiguration(): void
    {
        $declarationFile = dirname(__DIR__).'/Fixtures/DeclarationFiles/invalid-empty.php';

        $this->expectExceptionObject(
            new Src\Exception\DeclarationFileIsInvalid($declarationFile),
        );

        $this->subject->parse($declarationFile);
    }

    #[Framework\Attributes\Test]
    public function parseThrowsExceptionIfDeclarationFileProvidesInvalidExtensionConfiguration(): void
    {
        $declarationFile = dirname(__DIR__).'/Fixtures/DeclarationFiles/invalid-no-array.php';

        $this->expectExceptionObject(
            new Src\Exception\DeclarationFileIsInvalid($declarationFile),
        );

        $this->subject->parse($declarationFile);
    }

    #[Framework\Attributes\Test]
    public function parseReturnsExtensionConfiguration(): void
    {
        $declarationFile = dirname(__DIR__).'/Fixtures/DeclarationFiles/ext_emconf.php';

        $expected = [
            'title' => 'foo',
        ];

        self::assertSame($expected, $this->subject->parse($declarationFile));
    }

    #[Framework\Attributes\Test]
    public function parseThrowsTemporaryGlobalStateAway(): void
    {
        $declarationFile = dirname(__DIR__).'/Fixtures/DeclarationFiles/ext_emconf.php';

        $this->subject->parse($declarationFile);

        /* @phpstan-ignore staticMethod.alreadyNarrowedType, isset.variable */
        self::assertFalse(isset($EM_CONF));
        /* @phpstan-ignore staticMethod.alreadyNarrowedType, isset.variable */
        self::assertFalse(isset($_EXTKEY));
    }
}
