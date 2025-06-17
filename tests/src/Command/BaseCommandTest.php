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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Command;

use EliasHaeussler\Typo3VendorBundler as Src;
use EliasHaeussler\Typo3VendorBundler\Tests;
use PHPUnit\Framework;
use Symfony\Component\Console;

use function dirname;

/**
 * BaseCommandTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Command\BaseCommand::class)]
final class BaseCommandTest extends Framework\TestCase
{
    private Console\Output\BufferedOutput $output;
    private Tests\Fixtures\Classes\DummyCommand $command;

    public function setUp(): void
    {
        $this->output = new Console\Output\BufferedOutput();
        $this->command = new Tests\Fixtures\Classes\DummyCommand('foo', output: $this->output);
    }

    #[Framework\Attributes\Test]
    public function readConfigFileAutoDetectsConfigFileIfNoConfigFileIsGiven(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures/ConfigFiles';

        $actual = $this->command->readConfigFile(null, $rootPath);

        self::assertInstanceOf(Src\Config\Typo3VendorBundlerConfig::class, $actual);
    }

    #[Framework\Attributes\Test]
    public function readConfigFileReturnsEmptyConfigObjectIfNoConfigFileCouldBeFound(): void
    {
        $rootPath = __DIR__;

        $actual = $this->command->readConfigFile(null, $rootPath);

        self::assertEquals(
            new Src\Config\Typo3VendorBundlerConfig(rootPath: $rootPath),
            $actual,
        );
    }

    #[Framework\Attributes\Test]
    public function readConfigFileConvertsRelativeConfigFilePathToAbsolutePath(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures/ConfigFiles';

        /** @var Src\Config\Typo3VendorBundlerConfig $expected */
        $expected = include $rootPath.'/valid-config.php';
        $expected->setRootPath(dirname($rootPath));

        $actual = $this->command->readConfigFile('valid-config.php', $rootPath);

        self::assertEquals($expected, $actual);
    }

    #[Framework\Attributes\Test]
    public function readConfigFileDecoratesMappingErrors(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures/ConfigFiles';

        $actual = $this->command->readConfigFile('invalid-config.json', $rootPath);

        self::assertNull($actual);
        self::assertStringContainsString(
            'Unexpected key(s) `foo`, expected `autoload`, `pathToVendorLibraries`, `rootPath`.',
            $this->output->fetch(),
        );
    }

    #[Framework\Attributes\Test]
    public function readConfigFileDisplaysExceptionMessages(): void
    {
        $rootPath = dirname(__DIR__).'/Fixtures/ConfigFiles';

        $actual = $this->command->readConfigFile('foo.json', $rootPath);

        self::assertNull($actual);
        self::assertMatchesRegularExpression(
            '/File ".+\/foo\.json"\s+does\s+not\s+exist\./',
            $this->output->fetch(),
        );
    }
}
