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

namespace EliasHaeussler\Typo3VendorBundler\Resource;

use EliasHaeussler\Typo3VendorBundler\Exception;
use Symfony\Component\Filesystem;

use function array_key_exists;
use function is_array;

/**
 * ExtEmConfParser.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final readonly class ExtEmConfParser
{
    private Filesystem\Filesystem $filesystem;

    public function __construct(
    ) {
        $this->filesystem = new Filesystem\Filesystem();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception\DeclarationFileIsInvalid
     * @throws Exception\FileDoesNotExist
     */
    public function parse(string $declarationFile): array
    {
        // Exit if declaration file does not exist
        if (!$this->filesystem->exists($declarationFile)) {
            throw new Exception\FileDoesNotExist($declarationFile);
        }

        try {
            /** @var array<string, mixed> $EM_CONF */
            $EM_CONF = [];
            $_EXTKEY = 'dummy';

            // Load ext_emconf configuration
            include $declarationFile;

            // Throw exception if no configuration was written
            if (!array_key_exists($_EXTKEY, $EM_CONF)) {
                throw new Exception\DeclarationFileIsInvalid($declarationFile);
            }

            $extEmConf = $EM_CONF[$_EXTKEY];
        } finally {
            // Throw away temporary state
            unset($EM_CONF, $_EXTKEY);
        }

        // Exit if configuration is not an array
        if (!is_array($extEmConf)) {
            throw new Exception\DeclarationFileIsInvalid($declarationFile);
        }

        /* @phpstan-ignore return.type */
        return $extEmConf;
    }
}
