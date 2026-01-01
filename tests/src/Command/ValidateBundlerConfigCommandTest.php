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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Command;

use Composer\Console\Application;
use EliasHaeussler\Typo3VendorBundler as Src;
use PHPUnit\Framework;
use Symfony\Component\Console;

/**
 * ValidateBundlerConfigCommandTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Command\ValidateBundlerConfigCommand::class)]
final class ValidateBundlerConfigCommandTest extends Framework\TestCase
{
    private Console\Tester\CommandTester $commandTester;

    public function setUp(): void
    {
        $application = new Application();
        $command = new Src\Command\ValidateBundlerConfigCommand();
        $command->setApplication($application);

        $this->commandTester = new Console\Tester\CommandTester($command);
    }

    #[Framework\Attributes\Test]
    public function executeDisplaysErrorMessageAndFailsIfNoConfigIsAvailable(): void
    {
        $this->commandTester->execute([]);

        self::assertSame(Console\Command\Command::INVALID, $this->commandTester->getStatusCode());
        self::assertStringContainsString('No config file could be detected.', $this->commandTester->getDisplay());
    }

    #[Framework\Attributes\Test]
    public function executeDisplaysErrorMessageAndFailsIfConfigFileIsNotValid(): void
    {
        $this->commandTester->execute([
            '--config' => dirname(__DIR__).'/Fixtures/ConfigFiles/invalid-config.json',
        ]);

        self::assertSame(Console\Command\Command::INVALID, $this->commandTester->getStatusCode());
        self::assertStringContainsString('*root*: Unexpected key(s) `foo`', $this->commandTester->getDisplay());
    }

    #[Framework\Attributes\Test]
    public function executeDisplaysErrorMessageAndFailsIfConfigFileCannotBeRead(): void
    {
        $this->commandTester->execute([
            '--config' => 'foo',
        ]);

        self::assertSame(Console\Command\Command::INVALID, $this->commandTester->getStatusCode());
        self::assertMatchesRegularExpression(
            '/\[ERROR] File ".+\/foo" does not exist\./',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeDisplaysSuccessMessageIfConfigIsValid(): void
    {
        $this->commandTester->execute([
            '--config' => dirname(__DIR__).'/Fixtures/ConfigFiles/valid-config.json',
        ]);

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            '[OK] Congratulations, your config file is valid.',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeDisplaysAdditionalInformationOnVerboseOutput(): void
    {
        $this->commandTester->execute(
            [
                '--config' => dirname(__DIR__).'/Fixtures/ConfigFiles/valid-config.json',
            ],
            [
                'verbosity' => Console\Output\OutputInterface::VERBOSITY_VERBOSE,
            ],
        );

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('Found config file', $output);
        self::assertStringContainsString('Config file contains no invalid options.', $output);
    }
}
