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

namespace EliasHaeussler\Typo3VendorBundler\Exception;

use CycloneDX\Core;
use EliasHaeussler\Typo3VendorBundler\Resource;

/**
 * BomFormatIsNotSupported.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final class BomFormatIsNotSupported extends Exception
{
    public function __construct(Resource\BomFormat|string $format, ?Core\Spec\Version $version = null)
    {
        if ($format instanceof Resource\BomFormat) {
            $format = $format->value;
        }

        if (null !== $version) {
            $message = sprintf('The SBOM file format "%s" is not supported by CycloneDX version "%s".', $format, $version->value);
        } else {
            $message = sprintf('The SBOM file format "%s" is not supported.', $format);
        }

        parent::__construct($message, 1766355150);
    }
}
