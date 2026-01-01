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
use Composer\Package;
use Composer\Repository;

use function array_key_exists;
use function in_array;

/**
 * DependencyExtractor.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final class DependencyExtractor
{
    public function extract(Composer $composer): DependencySet
    {
        $problems = [];

        // Holds all packages already bundled by framework packages
        $providedPackages = [];

        // Holds all packages required by the relevant direct dependencies
        $requiredPackages = [];

        // Extract all relevant direct dependencies
        [$packages, $frameworkPackages] = $this->extractDirectDependencies($composer, $problems);

        // Register all framework dependencies to exclude from registered packages,
        // since these are already provided by the framework packages.
        foreach ($frameworkPackages as $frameworkPackage) {
            $this->collectTransitiveDependencies($composer, $frameworkPackage, $providedPackages);
        }

        // Remove excluded packages from registered packages
        foreach ($packages as $packageName => $package) {
            if (isset($providedPackages[$packageName])) {
                unset($packages[$packageName]);
            }
        }

        // Bump all packages to their best matching version
        $this->applyBestMatchingPackageVersions($composer, $packages, $problems);

        // Collect all dependencies, which must be included in the final dependency set.
        // This includes all currently registered packages as well as defined transitive dependencies.
        foreach ($packages as $package) {
            $this->collectTransitiveDependencies($composer, $package, $requiredPackages);
        }

        // Mark all packages which are already bundled by framework packages as excluded
        $excludedPackages = array_intersect_key($requiredPackages, $providedPackages);

        return new DependencySet($packages, $excludedPackages, $problems);
    }

    /**
     * @param array<string, list<DependencyExtractionProblem>> $problems
     *
     * @return array{
     *     array<string, Package\PackageInterface>,
     *     array<string, Package\PackageInterface>,
     * }
     */
    private function extractDirectDependencies(Composer $composer, array &$problems): array
    {
        $packages = [];
        $frameworkPackages = [];

        foreach ($composer->getPackage()->getRequires() as $packageName => $link) {
            // Skip platform packages (php, ext-*, composer, ...)
            if ($this->isPlatformPackage($link->getTarget())) {
                continue;
            }

            $package = $composer->getRepositoryManager()->findPackage($link->getTarget(), $link->getConstraint());

            if (null === $package) {
                $this->addProblem($problems, $packageName, DependencyExtractionProblem::RequirementNotResolvable);
                continue;
            }

            if ($this->isFrameworkPackage($package)) {
                // Register framework packages (core extensions)
                $frameworkPackages[$packageName] = $package;
            } elseif (!$this->isExtensionPackage($package)) {
                // Register all non-extension packages
                $packages[$packageName] = $package;
            }
        }

        return [$packages, $frameworkPackages];
    }

    /**
     * @param array<string, Package\PackageInterface>          $packages
     * @param array<string, list<DependencyExtractionProblem>> $problems
     */
    private function applyBestMatchingPackageVersions(Composer $composer, array &$packages, array &$problems): void
    {
        $requirements = $composer->getPackage()->getRequires();

        // Instantiate Composer version collector from currently loaded repositories
        $repositorySet = new Repository\RepositorySet(
            $composer->getPackage()->getMinimumStability(),
            $composer->getPackage()->getStabilityFlags(),
        );
        $repositorySet->addRepository(
            new Repository\CompositeRepository($composer->getRepositoryManager()->getRepositories()),
        );
        $versionSelector = new Package\Version\VersionSelector(
            $repositorySet,
            new Repository\PlatformRepository([], $composer->getConfig()->get('platform')),
        );

        foreach ($packages as $packageName => $package) {
            $resolvedPackage = $versionSelector->findBestCandidate(
                $packageName,
                $requirements[$packageName]->getPrettyConstraint(),
            );

            if (false === $resolvedPackage) {
                $this->addProblem($problems, $packageName, DependencyExtractionProblem::NoMatchingVersionFound);
                unset($packages[$packageName]);
            } else {
                $packages[$packageName] = $resolvedPackage;
            }
        }
    }

    /**
     * @param array<string, Package\PackageInterface> $collectedPackages
     */
    private function collectTransitiveDependencies(
        Composer $composer,
        Package\PackageInterface $package,
        array &$collectedPackages,
    ): void {
        foreach ($package->getRequires() as $link) {
            $packageName = $link->getTarget();

            // Skip already processed packages
            if (array_key_exists($packageName, $collectedPackages)) {
                continue;
            }

            $dependentPackage = $composer->getRepositoryManager()->findPackage($packageName, $link->getConstraint());

            if (null !== $dependentPackage) {
                $collectedPackages[$packageName] = $dependentPackage;

                // Run dependency collector recursively
                $this->collectTransitiveDependencies($composer, $dependentPackage, $collectedPackages);
            }
        }
    }

    private function isPlatformPackage(string $packageName): bool
    {
        return Repository\PlatformRepository::isPlatformPackage($packageName);
    }

    private function isFrameworkPackage(Package\PackageInterface $package): bool
    {
        return 'typo3-cms-framework' === $package->getType();
    }

    private function isExtensionPackage(Package\PackageInterface $package): bool
    {
        return 'typo3-cms-extension' === $package->getType();
    }

    /**
     * @param array<string, list<DependencyExtractionProblem>> $problems
     */
    private function addProblem(
        array &$problems,
        string $packageName,
        DependencyExtractionProblem $problem,
    ): void {
        $problems[$packageName] ??= [];

        if (!in_array($problem, $problems[$packageName], true)) {
            $problems[$packageName][] = $problem;
        }
    }
}
