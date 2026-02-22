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

use EliasHaeussler\PhpCsFixerConfig;
use Symfony\Component\Finder;

$header = PhpCsFixerConfig\Rules\Header::create(
    'eliashaeussler/typo3-vendor-bundler',
    PhpCsFixerConfig\Package\Type::ComposerPackage,
    PhpCsFixerConfig\Package\Author::create('Elias Häußler', 'elias@haeussler.dev'),
    PhpCsFixerConfig\Package\CopyrightRange::from(2025),
    PhpCsFixerConfig\Package\License::GPL3OrLater,
);

return PhpCsFixerConfig\Config::create()
    ->withRule($header)
    ->withFinder(static fn (Finder\Finder $finder) => $finder->in(dirname(__DIR__, 2)))
    ->setCacheFile('build/cgl/cache/php-cs-fixer/.php-cs-fixer.cache')
;
