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

namespace EliasHaeussler\Typo3VendorBundler\Tests;

use Composer\Factory;
use Composer\IO;
use Composer\Package;
use PHPUnit\Framework;
use Symfony\Component\Filesystem;

/**
 * ExtensionFixtureBasedTestCase.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
abstract class ExtensionFixtureBasedTestCase extends Framework\TestCase
{
    protected Filesystem\Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem\Filesystem();
    }

    protected function createTemporaryFixture(string $sourceExtension = 'valid'): string
    {
        $sourcePath = self::getFixturePath($sourceExtension);
        $targetPath = $this->createTemporaryDirectory();

        $this->filesystem->mirror($sourcePath, $targetPath);

        return $targetPath;
    }

    protected function createTemporaryDirectory(): string
    {
        $targetPath = self::getFixturePath('temporary');

        $this->filesystem->remove($targetPath);
        $this->filesystem->mkdir($targetPath);

        return $targetPath;
    }

    protected static function getFixturePath(string $extension = 'valid'): string
    {
        return __DIR__.'/Fixtures/Extensions/'.$extension;
    }

    protected function parseComposerJson(string $filename): Package\RootPackageInterface
    {
        self::assertFileExists($filename);

        return Factory::create(new IO\NullIO(), $filename)->getPackage();
    }

    protected function tearDown(): void
    {
        $temporaryFixturePath = self::getFixturePath('temporary');

        if ($this->filesystem->exists($temporaryFixturePath)) {
            $this->filesystem->remove($temporaryFixturePath);
        }
    }
}
