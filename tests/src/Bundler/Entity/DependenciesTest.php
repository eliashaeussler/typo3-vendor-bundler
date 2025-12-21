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
 * DependenciesTest.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Bundler\Entity\Dependencies::class)]
final class DependenciesTest extends Framework\TestCase
{
    private Src\Bundler\Entity\Dependencies $subject;

    public function setUp(): void
    {
        $this->subject = new Src\Bundler\Entity\Dependencies('sbom.json', __DIR__);
    }

    #[Framework\Attributes\Test]
    public function sbomFileReturnsFilename(): void
    {
        self::assertSame(__DIR__.'/sbom.json', $this->subject->sbomFile());
    }

    #[Framework\Attributes\Test]
    public function sbomFileReturnsFilenameAsRelativePath(): void
    {
        self::assertSame('sbom.json', $this->subject->sbomFile(true));
    }
}
