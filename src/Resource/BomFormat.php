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

namespace EliasHaeussler\Typo3VendorBundler\Resource;

use CycloneDX\Core;
use EliasHaeussler\Typo3VendorBundler\Exception;
use Symfony\Component\Filesystem;

/**
 * BomFormat.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
enum BomFormat: string
{
    case Json = 'json';
    case Xml = 'xml';

    /**
     * @throws Exception\BomFormatIsNotSupported
     */
    public static function fromFile(string $filename): self
    {
        $extension = Filesystem\Path::getExtension($filename, true);
        $format = self::tryFrom($extension);

        if (null === $format) {
            throw new Exception\BomFormatIsNotSupported($extension);
        }

        return $format;
    }

    public function supports(Core\Spec\Version $version): bool
    {
        $format = match ($this) {
            self::Json => Core\Spec\Format::JSON,
            self::Xml => Core\Spec\Format::XML,
        };

        /* @phpstan-ignore method.internalInterface */
        return Core\Spec\SpecFactory::makeForVersion($version)->isSupportedFormat($format);
    }

    public function createSerializer(Core\Spec\Version $version): Core\Serialization\Serializer
    {
        $spec = Core\Spec\SpecFactory::makeForVersion($version);

        return match ($this) {
            self::Json => new Core\Serialization\JsonSerializer(new Core\Serialization\JSON\NormalizerFactory($spec)),
            self::Xml => new Core\Serialization\XmlSerializer(new Core\Serialization\DOM\NormalizerFactory($spec)),
        };
    }

    public function createValidator(Core\Spec\Version $version): Core\Validation\Validator
    {
        $spec = Core\Spec\SpecFactory::makeForVersion($version);

        return match ($this) {
            self::Json => new Core\Validation\Validators\JsonStrictValidator($spec),
            self::Xml => new Core\Validation\Validators\XmlValidator($spec),
        };
    }
}
