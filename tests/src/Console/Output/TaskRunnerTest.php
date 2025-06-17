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

namespace EliasHaeussler\Typo3VendorBundler\Tests\Console\Output;

use EliasHaeussler\Typo3VendorBundler as Src;
use Exception;
use PHPUnit\Framework;
use Symfony\Component\Console;
use Throwable;

use function trim;

/**
 * TaskRunnerTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Console\Output\TaskRunner::class)]
final class TaskRunnerTest extends Framework\TestCase
{
    private Console\Output\BufferedOutput $output;
    private Src\Console\Output\TaskRunner $subject;

    public function setUp(): void
    {
        $this->output = new Console\Output\BufferedOutput();
        $this->subject = new Src\Console\Output\TaskRunner($this->output);
    }

    #[Framework\Attributes\Test]
    public function runReturnsReturnValueFromTask(): void
    {
        $task = static fn () => 'Hello World!';

        $actual = $this->subject->run('Let\'s go', $task);

        /* @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertSame('Hello World!', $actual);
    }

    #[Framework\Attributes\Test]
    public function runDisplaysMessageAndShowsDoneMessageOnSuccessfulTaskExecution(): void
    {
        $task = static fn () => 'Hello World!';

        $actual = $this->subject->run('Let\'s go', $task);

        self::assertSame('Let\'s go... Done', trim($this->output->fetch()));
    }

    #[Framework\Attributes\Test]
    public function runDisplaysMessageAndShowsFailedMessageIfExceptionIsThrown(): void
    {
        $exception = new Exception('Something went wrong');
        $task = static fn () => throw $exception;

        $actual = null;

        try {
            $this->subject->run('Let\'s go', $task);
        } catch (Throwable $actual) {
        }

        self::assertSame($exception, $actual);
        self::assertSame('Let\'s go... Failed', trim($this->output->fetch()));
    }

    #[Framework\Attributes\Test]
    public function runDoesNotDisplayMessageIfSeverityDoesNotMatch(): void
    {
        $task = static fn () => 'Hello World!';

        $actual = $this->subject->run('Let\'s go', $task, Console\Output\OutputInterface::VERBOSITY_VERBOSE);

        self::assertSame('', $this->output->fetch());
    }
}
