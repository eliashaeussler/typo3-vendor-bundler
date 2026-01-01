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

use Composer\Composer;
use Composer\Factory;
use Composer\IO;
use Composer\Package;
use EliasHaeussler\Typo3VendorBundler\Exception;
use Symfony\Component\Filesystem;
use Throwable;

use function is_array;
use function ksort;

/**
 * DependencySet.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final readonly class DependencySet
{
    /**
     * @param array<string, Package\PackageInterface>          $requiredPackages
     * @param array<string, Package\PackageInterface>          $excludedPackages
     * @param array<string, list<DependencyExtractionProblem>> $extractionProblems
     */
    public function __construct(
        public array $requiredPackages,
        public array $excludedPackages = [],
        public array $extractionProblems = [],
    ) {}

    /**
     * @return array<string, string>
     */
    public function requirements(): array
    {
        $requirements = [];

        foreach ($this->requiredPackages as $requirement) {
            $requirements[$requirement->getPrettyName()] = $requirement->getPrettyVersion();
        }

        ksort($requirements);

        return $requirements;
    }

    /**
     * @return array<string, string>
     */
    public function exclusions(): array
    {
        $exclusions = [];

        foreach ($this->excludedPackages as $exclusion) {
            $exclusions[$exclusion->getPrettyName()] = '*';
        }

        ksort($exclusions);

        return $exclusions;
    }

    /**
     * @return list<string>
     */
    public function problems(): array
    {
        $messages = [];

        foreach ($this->extractionProblems as $packageName => $packageProblems) {
            foreach ($packageProblems as $problem) {
                $messages[] = $problem->describe($packageName);
            }
        }

        return $messages;
    }

    /**
     * @throws Exception\DeclarationFileIsInvalid
     */
    public function dumpToFile(string $filename, ?Composer $origin = null): void
    {
        $filesystem = new Filesystem\Filesystem();

        // Make sure composer.json file exists
        if (!$filesystem->exists($filename)) {
            $filesystem->dumpFile($filename, '{}');
        }

        try {
            $composer = Factory::create(new IO\NullIO(), $filename);
        } catch (Throwable $exception) {
            throw new Exception\DeclarationFileIsInvalid($filename, previous: $exception);
        }

        $name = $origin?->getPackage()->getName() ?? '';

        if (!str_contains($name, '/')) {
            $name = uniqid('typo3-vendor-bundler/');
        }

        $configSource = $composer->getConfig()->getConfigSource();
        $configSource->addProperty('name', $name.'-libs');
        $configSource->addConfigSetting('allow-plugins', false);
        $configSource->addConfigSetting('lock', false);

        foreach ($this->requirements() as $packageName => $constraint) {
            $configSource->addLink('require', $packageName, $constraint);
        }

        foreach ($this->exclusions() as $packageName => $constraint) {
            $configSource->addLink('provide', $packageName, $constraint);
        }

        foreach ($origin?->getConfig()->getRepositories() ?? [] as $repositoryIndex => $repository) {
            // Add only non-packagist repositories
            if ('packagist.org' !== $repositoryIndex && is_array($repository)) {
                $configSource->addRepository('', $repository);
            }
        }
    }
}
