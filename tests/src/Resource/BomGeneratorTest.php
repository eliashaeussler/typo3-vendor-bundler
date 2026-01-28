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

use EliasHaeussler\Typo3VendorBundler as Src;
use EliasHaeussler\Typo3VendorBundler\Tests;
use PHPUnit\Framework;

/**
 * BomGeneratorTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Resource\BomGenerator::class)]
final class BomGeneratorTest extends Tests\ExtensionFixtureBasedTestCase
{
    private string $rootPath;
    private Src\Resource\BomGenerator $subject;
    private Src\Resource\Composer $composer;

    public function setUp(): void
    {
        parent::setUp();

        $this->rootPath = $this->createTemporaryFixture();
        $this->subject = new Src\Resource\BomGenerator($this->rootPath);
        $this->composer = Src\Resource\Composer::create($this->rootPath.'/libs/composer.json');
    }

    #[Framework\Attributes\Test]
    public function generateThrowsExceptionIfDependenciesAreNotInstalled(): void
    {
        $this->filesystem->remove($this->rootPath.'/libs/composer.lock');

        $this->expectExceptionObject(
            new Src\Exception\ComposerDependenciesAreNotInstalled(),
        );

        $this->subject->generate($this->composer->composer);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function generateReturnsBomWithRootComponent(): void
    {
        $this->composer->install();

        $actual = $this->subject->generate($this->composer->composer);

        $rootComponent = $actual->getMetadata()->getComponent();

        self::assertNotNull($rootComponent);
        self::assertNull($rootComponent->getGroup());
        self::assertSame('__root__', $rootComponent->getName());
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function generateReturnsBomWithTools(): void
    {
        $this->composer->install();

        $actual = $this->subject->generate($this->composer->composer);

        $tools = $actual->getMetadata()->getTools();

        self::assertCount(3, $tools);
        self::assertSame('composer', $tools->getItems()[0]->getName());
        self::assertSame('cyclonedx', $tools->getItems()[1]->getVendor());
        self::assertSame('cyclonedx-library', $tools->getItems()[1]->getName());
        self::assertSame('eliashaeussler', $tools->getItems()[2]->getVendor());
        self::assertSame('typo3-vendor-bundler', $tools->getItems()[2]->getName());
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function generateReturnsBomWithDevDependencies(): void
    {
        $this->composer->install();

        $actual = $this->subject->generate($this->composer->composer);

        $components = $actual->getComponents();

        self::assertGreaterThanOrEqual(2, $components->count());
        self::assertNotSame([], $components->findItem('yaml', 'symfony'));
        self::assertNotSame([], $components->findItem('event-dispatcher-contracts', 'symfony'));
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\WithoutErrorHandler]
    public function generateReturnsBomWithoutDevDependencies(): void
    {
        $this->composer->install(false);

        $actual = $this->subject->generate($this->composer->composer, false);

        $components = $actual->getComponents();

        self::assertGreaterThanOrEqual(1, $components->count());
        self::assertNotSame([], $components->findItem('yaml', 'symfony'));
        self::assertSame([], $components->findItem('event-dispatcher-contracts', 'symfony'));
    }
}
