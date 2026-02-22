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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Config;

use CuyZ\Valinor;
use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;
use Symfony\Component\Filesystem;

use function dirname;

/**
 * ConfigReaderTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Config\ConfigReader::class)]
final class ConfigReaderTest extends Framework\TestCase
{
    private Src\Config\ConfigReader $subject;

    public function setUp(): void
    {
        $this->subject = new Src\Config\ConfigReader();
    }

    #[Framework\Attributes\Test]
    public function readFromFileThrowsExceptionIfFileDoesNotExist(): void
    {
        $this->expectExceptionObject(
            new Src\Exception\FileDoesNotExist('foo'),
        );

        $this->subject->readFromFile('foo');
    }

    #[Framework\Attributes\Test]
    public function readFromFileThrowsExceptionOnUnsupportedConfigFile(): void
    {
        $file = dirname(__DIR__, 2).'/README.md';

        $this->expectExceptionObject(
            new Src\Exception\ConfigFileIsNotSupported($file),
        );

        $this->subject->readFromFile($file);
    }

    #[Framework\Attributes\Test]
    public function readFromFileThrowsExceptionOnInvalidPhpFile(): void
    {
        $file = dirname(__DIR__).'/Fixtures/ConfigFiles/invalid-config.php';

        $this->expectException(Src\Exception\ConfigFileIsInvalid::class);

        $this->subject->readFromFile($file);
    }

    #[Framework\Attributes\Test]
    public function readFromFileReturnsConfigFromPhpFile(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures';
        $file = $rootPath.'/ConfigFiles/valid-config.php';

        $expected = new Src\Config\Typo3VendorBundlerConfig(
            pathToVendorLibraries: 'foo',
            rootPath: $rootPath,
        );

        self::assertEquals($expected, $this->subject->readFromFile($file));
    }

    #[Framework\Attributes\Test]
    public function readFromFileReturnsConfigFromClosureInPhpFile(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures';
        $file = $rootPath.'/ConfigFiles/valid-config-with-closure.php';

        $expected = new Src\Config\Typo3VendorBundlerConfig(
            pathToVendorLibraries: 'foo',
            rootPath: $rootPath,
        );

        self::assertEquals($expected, $this->subject->readFromFile($file));
    }

    #[Framework\Attributes\Test]
    public function readFromFileThrowsExceptionOnInvalidJsonFile(): void
    {
        $file = dirname(__DIR__).'/Fixtures/ConfigFiles/invalid-config.json';

        $this->expectException(Valinor\Mapper\MappingError::class);

        $this->subject->readFromFile($file);
    }

    #[Framework\Attributes\Test]
    public function readFromFileReturnsMappedConfigFromJsonFile(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures';
        $file = $rootPath.'/ConfigFiles/valid-config.json';

        $expected = new Src\Config\Typo3VendorBundlerConfig(
            pathToVendorLibraries: 'foo',
            rootPath: $rootPath,
        );

        self::assertEquals($expected, $this->subject->readFromFile($file));
    }

    #[Framework\Attributes\Test]
    public function readFromFileThrowsExceptionOnInvalidYamlFile(): void
    {
        $file = dirname(__DIR__).'/Fixtures/ConfigFiles/invalid-config.yaml';

        $this->expectExceptionObject(
            new Src\Exception\ConfigFileIsInvalid($file),
        );

        $this->subject->readFromFile($file);
    }

    #[Framework\Attributes\Test]
    public function readFromFileReturnsMappedConfigFromYamlFile(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures';
        $file = $rootPath.'/ConfigFiles/valid-config.yaml';

        $expected = new Src\Config\Typo3VendorBundlerConfig(
            pathToVendorLibraries: 'foo',
            rootPath: $rootPath,
        );

        self::assertEquals($expected, $this->subject->readFromFile($file));
    }

    #[Framework\Attributes\Test]
    public function readFromFileCalculatesRootPathBasedOnConfigFileLocation(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures/ConfigFiles';
        $file = $rootPath.'/valid-config-without-root-path.json';

        $expected = new Src\Config\Typo3VendorBundlerConfig(
            pathToVendorLibraries: 'foo',
            rootPath: $rootPath,
        );

        self::assertEquals($expected, $this->subject->readFromFile($file));
    }

    #[Framework\Attributes\Test]
    public function detectFileReturnsNullIfNoConfigFilesAreAvailableInGivenRootPath(): void
    {
        self::assertNull($this->subject->detectFile(__DIR__));
    }

    #[Framework\Attributes\Test]
    public function detectFileReturnsAutoDetectedFileWithinGivenRootPath(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures/ConfigFiles';
        $expected = Filesystem\Path::join($rootPath, 'typo3-vendor-bundler.php');

        self::assertSame($expected, $this->subject->detectFile($rootPath));
    }
}
