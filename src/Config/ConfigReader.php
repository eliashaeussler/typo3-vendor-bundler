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

namespace EliasHaeussler\Typo3VendorBundler\Config;

use CuyZ\Valinor;
use EliasHaeussler\Typo3VendorBundler\Exception;
use SplFileObject;
use Symfony\Component\Filesystem;
use Symfony\Component\Yaml;

use function dirname;
use function is_callable;

/**
 * ConfigReader.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final readonly class ConfigReader
{
    private Filesystem\Filesystem $filesystem;
    private Valinor\Mapper\TreeMapper $mapper;

    public function __construct()
    {
        $this->filesystem = new Filesystem\Filesystem();
        $this->mapper = $this->createMapper();
    }

    /**
     * @throws Exception\ConfigFileIsInvalid
     * @throws Exception\ConfigFileIsNotSupported
     * @throws Exception\FileDoesNotExist
     * @throws Valinor\Mapper\MappingError
     */
    public function readFromFile(string $file): Typo3VendorBundlerConfig
    {
        if (!$this->filesystem->exists($file)) {
            throw new Exception\FileDoesNotExist($file);
        }

        $extension = Filesystem\Path::getExtension($file, true);

        if ('php' === $extension) {
            $config = $this->parsePhpFile($file);
        } else {
            $source = match ($extension) {
                'json' => Valinor\Mapper\Source\Source::file(new SplFileObject($file)),
                'yaml', 'yml' => Valinor\Mapper\Source\Source::array($this->parseYamlFile($file)),
                default => throw new Exception\ConfigFileIsNotSupported($file),
            };
            $config = $this->mapper->map(Typo3VendorBundlerConfig::class, $source);
        }

        if (null === $config->rootPath()) {
            $config->setRootPath(dirname($file));
        } elseif (!Filesystem\Path::isAbsolute($config->rootPath())) {
            $config->setRootPath(
                Filesystem\Path::makeAbsolute($config->rootPath(), dirname($file)),
            );
        }

        return $config;
    }

    public function detectFile(string $rootPath): ?string
    {
        $filenames = [
            'typo3-vendor-bundler.php',
            'typo3-vendor-bundler.json',
            'typo3-vendor-bundler.yaml',
            'typo3-vendor-bundler.yml',
        ];

        foreach ($filenames as $filename) {
            $path = Filesystem\Path::join($rootPath, $filename);

            if ($this->filesystem->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @throws Exception\ConfigFileIsInvalid
     */
    private function parsePhpFile(string $file): Typo3VendorBundlerConfig
    {
        $returnValue = require $file;

        if ($returnValue instanceof Typo3VendorBundlerConfig) {
            return $returnValue;
        }

        if (!is_callable($returnValue)) {
            throw new Exception\ConfigFileIsInvalid($file);
        }

        $config = $returnValue();

        if (!($config instanceof Typo3VendorBundlerConfig)) {
            throw new Exception\ConfigFileIsInvalid($file);
        }

        return $config;
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws Exception\ConfigFileIsInvalid
     */
    private function parseYamlFile(string $file): array
    {
        $yaml = Yaml\Yaml::parseFile($file);

        if (!is_array($yaml)) {
            throw new Exception\ConfigFileIsInvalid($file);
        }

        return $yaml;
    }

    private function createMapper(): Valinor\Mapper\TreeMapper
    {
        return (new Valinor\MapperBuilder())
            ->allowPermissiveTypes()
            ->mapper()
        ;
    }
}
