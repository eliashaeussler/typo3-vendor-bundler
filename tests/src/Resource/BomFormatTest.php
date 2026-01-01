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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Resource;

use CycloneDX\Core;
use EliasHaeussler\Typo3VendorBundler as Src;
use Generator;
use PHPUnit\Framework;

/**
 * BomFormatTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Resource\BomFormat::class)]
final class BomFormatTest extends Framework\TestCase
{
    #[Framework\Attributes\Test]
    public function fromFileThrowsExceptionOnUnsupportedFormat(): void
    {
        $this->expectExceptionObject(
            new Src\Exception\BomFormatIsNotSupported('php'),
        );

        Src\Resource\BomFormat::fromFile(__FILE__);
    }

    /**
     * @return Generator<string, array{string, Src\Resource\BomFormat}>
     */
    public static function fromFileReturnsObjectFromExtractedFileExtensionDataProvider(): Generator
    {
        yield 'json' => ['/foo/baz/sbom.json', Src\Resource\BomFormat::Json];
        yield 'xml' => ['/foo/baz/sbom.xml', Src\Resource\BomFormat::Xml];
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('fromFileReturnsObjectFromExtractedFileExtensionDataProvider')]
    public function fromFileReturnsObjectFromExtractedFileExtension(
        string $filename,
        Src\Resource\BomFormat $expected,
    ): void {
        self::assertSame($expected, Src\Resource\BomFormat::fromFile($filename));
    }

    /**
     * @return Generator<string, array{Src\Resource\BomFormat, Core\Spec\Version, bool}>
     */
    public static function supportsReturnsTrueIfGivenVersionIsSupportedWithFormatDataProvider(): Generator
    {
        yield 'json with unsupported version' => [Src\Resource\BomFormat::Json, Core\Spec\Version::v1dot1, false];
        yield 'json with supported version' => [Src\Resource\BomFormat::Json, Core\Spec\Version::v1dot7, true];
        yield 'xml' => [Src\Resource\BomFormat::Xml, Core\Spec\Version::v1dot7, true];
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('supportsReturnsTrueIfGivenVersionIsSupportedWithFormatDataProvider')]
    public function supportsReturnsTrueIfGivenVersionIsSupportedWithFormat(
        Src\Resource\BomFormat $subject,
        Core\Spec\Version $version,
        bool $expected,
    ): void {
        self::assertSame($expected, $subject->supports($version));
    }

    /**
     * @return Generator<string, array{Src\Resource\BomFormat, class-string<Core\Serialization\Serializer>}>
     */
    public static function createSerializerReturnsSerializerDataProvider(): Generator
    {
        yield 'json' => [Src\Resource\BomFormat::Json, Core\Serialization\JsonSerializer::class];
        yield 'xml' => [Src\Resource\BomFormat::Xml, Core\Serialization\XmlSerializer::class];
    }

    /**
     * @param class-string<Core\Serialization\Serializer> $expected
     */
    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('createSerializerReturnsSerializerDataProvider')]
    public function createSerializerReturnsSerializer(Src\Resource\BomFormat $subject, string $expected): void
    {
        self::assertInstanceOf($expected, $subject->createSerializer(Core\Spec\Version::v1dot7));
    }

    /**
     * @return Generator<string, array{Src\Resource\BomFormat, class-string<Core\Validation\Validator>}>
     */
    public static function createValidatorReturnsValidatorDataProvider(): Generator
    {
        yield 'json' => [Src\Resource\BomFormat::Json, Core\Validation\Validators\JsonStrictValidator::class];
        yield 'xml' => [Src\Resource\BomFormat::Xml, Core\Validation\Validators\XmlValidator::class];
    }

    /**
     * @param class-string<Core\Validation\Validator> $expected
     */
    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('createValidatorReturnsValidatorDataProvider')]
    public function createValidatorReturnsValidator(Src\Resource\BomFormat $subject, string $expected): void
    {
        self::assertInstanceOf($expected, $subject->createValidator(Core\Spec\Version::v1dot7));
    }
}
