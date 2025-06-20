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

use Composer\Console\Application;
use EliasHaeussler\Typo3VendorBundler as Src;
use EliasHaeussler\Typo3VendorBundler\Tests;
use PHPUnit\Framework;
use Symfony\Component\Console;

use function sprintf;

/**
 * BundleCommandTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Command\BundleCommand::class)]
final class BundleCommandTest extends Framework\TestCase
{
    private Tests\Fixtures\Classes\DummyCommand $firstCommand;
    private Tests\Fixtures\Classes\DummyCommand $secondCommand;
    private Console\Tester\CommandTester $commandTester;

    public function setUp(): void
    {
        $this->firstCommand = new Tests\Fixtures\Classes\DummyCommand('first command');
        $this->secondCommand = new Tests\Fixtures\Classes\DummyCommand('second command');

        $application = new Application();
        $command = new Src\Command\BundleCommand([
            $this->firstCommand,
            $this->secondCommand,
        ]);
        $command->setApplication($application);

        $this->commandTester = new Console\Tester\CommandTester($command);
    }

    #[Framework\Attributes\Test]
    public function executeFailsIfAnyConfiguredBundlerFails(): void
    {
        $this->firstCommand->setCode(
            fn () => Console\Command\Command::FAILURE,
        );

        $actual = $this->commandTester->execute([]);

        self::assertSame(Console\Command\Command::FAILURE, $actual);
    }

    #[Framework\Attributes\Test]
    public function executePassesConfigToConfiguredBundlers(): void
    {
        $this->firstCommand->setCode(
            static function (Console\Input\InputInterface $input, Console\Output\OutputInterface $output): int {
                $config = $input->getOption('config');

                self::assertIsString($config);

                $output->writeln(
                    sprintf('Passed config "%s".', $config),
                );

                return Console\Command\Command::SUCCESS;
            },
        );

        $actual = $this->commandTester->execute([
            '--config' => 'foo',
        ]);

        self::assertSame(Console\Command\Command::SUCCESS, $actual);
        self::assertStringContainsString('Passed config "foo".', $this->commandTester->getDisplay());
    }

    #[Framework\Attributes\Test]
    public function executeExecutesConfiguredBundlers(): void
    {
        $this->firstCommand->setCode(
            static function (Console\Input\InputInterface $input, Console\Output\OutputInterface $output): int {
                $output->writeln('Hello world from first command!');

                return Console\Command\Command::SUCCESS;
            },
        );
        $this->secondCommand->setCode(
            static function (Console\Input\InputInterface $input, Console\Output\OutputInterface $output): int {
                $output->writeln('Hello world from second command!');

                return Console\Command\Command::SUCCESS;
            },
        );

        $actual = $this->commandTester->execute([]);

        self::assertSame(Console\Command\Command::SUCCESS, $actual);
        self::assertStringContainsString('Hello world from first command!', $this->commandTester->getDisplay());
        self::assertStringContainsString('Hello world from second command!', $this->commandTester->getDisplay());
    }
}
