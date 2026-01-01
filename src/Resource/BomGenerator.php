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
use Composer\Repository;
use Composer\Semver;
use CycloneDX\Contrib;
use CycloneDX\Core;
use DateTimeImmutable;
use EliasHaeussler\Typo3VendorBundler\Exception;
use Generator;
use PackageUrl\PackageUrl;
use Symfony\Component\Filesystem;

use function array_filter;
use function array_map;
use function implode;
use function str_contains;
use function trim;

/**
 * BomGenerator.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 *
 * @see https://github.com/CycloneDX/cyclonedx-php-composer/blob/v6.0.0/src/_internal/MakeBom/Builder.php
 */
final readonly class BomGenerator
{
    private Contrib\License\Factories\LicenseFactory $licenseFactory;
    private Composer $rootComposer;

    public function __construct(
        private string $rootPath,
    ) {
        $this->licenseFactory = new Contrib\License\Factories\LicenseFactory();
        $this->rootComposer = Factory::create(
            new IO\NullIO(),
            Filesystem\Path::join($this->rootPath, 'composer.json'),
        );
    }

    /**
     * @throws Exception\ComposerDependenciesAreNotInstalled
     */
    public function generate(Composer $composer, bool $includeDevDependencies = true): Core\Models\Bom
    {
        $locker = $composer->getLocker();

        // Fail if Composer dependencies are not installed and lock state cannot be determined
        if (!$locker->isLocked()) {
            throw new Exception\ComposerDependenciesAreNotInstalled();
        }

        $repository = $locker->getLockedRepository($includeDevDependencies);
        $packages = $repository->getPackages();
        $rootPackage = $this->rootComposer->getPackage();
        $rootComponent = $this->createComponentFromPackage($rootPackage);
        $components = [];

        $bom = new Core\Models\Bom();
        $bom->getMetadata()->setComponent($rootComponent);
        $bom->getMetadata()->setTimestamp(new DateTimeImmutable());
        $bom->getMetadata()->getTools()->addItems(...$this->createTools());
        $bom->setSerialNumber(Contrib\Bom\Utils\BomUtils::randomSerialNumber());

        // Convert each installed package to a BOM component
        foreach ($packages as $package) {
            $components[$package->getName()] = $this->createComponentFromPackage($package);
        }

        // Register BOM components
        $bom->getComponents()->addItems(...$components);

        // Build dependency tree for all installed packages
        foreach ($packages as $package) {
            $this->applyDependencyTree($package, $repository, $components);
        }

        // Build dependency tree for root package
        $components[$rootPackage->getName()] = $rootComponent;
        $this->applyDependencyTree($rootPackage, $repository, $components);

        return $bom;
    }

    private function createComponentFromPackage(Package\PackageInterface $package): Core\Models\Component
    {
        [$vendor, $name] = $this->splitPackageName($package->getName());
        $distReference = $package->getDistReference() ?? '';
        $sourceReference = $package->getSourceReference() ?? '';

        $component = new Core\Models\Component(Core\Enums\ComponentType::Library, $name);
        $component->setBomRefValue($package->getUniqueName());
        $component->setGroup($vendor);
        $component->setVersion($package->getPrettyVersion());
        $component->getExternalReferences()->addItems(...$this->createExternalReferencesFromPackage($package));

        if ('' !== $distReference) {
            $component->getProperties()->addItems(
                new Core\Models\Property(BomProperty::DistReference->value, $distReference),
            );
        }

        if ('' !== $sourceReference) {
            $component->getProperties()->addItems(
                new Core\Models\Property(BomProperty::SourceReference->value, $sourceReference),
            );
        }

        if ($package instanceof Package\CompletePackageInterface) {
            $component->setDescription($package->getDescription());
            $component->setAuthor($this->createAuthorFromPackage($package));
            $component->getLicenses()->addItems(...$this->createLicensesFromPackage($package));
        }

        $component->getProperties()->addItems(
            new Core\Models\Property(BomProperty::PackageType->value, $package->getType()),
        );

        $component->setPackageUrl($this->createPurlFromComponent($component));

        return $component;
    }

    private function createAuthorFromPackage(Package\CompletePackageInterface $package): string
    {
        return implode(
            ', ',
            array_filter(
                array_map(
                    static fn (array $author) => trim($author['name'] ?? ''),
                    $package->getAuthors(),
                ),
                static fn (string $author) => '' !== $author,
            ),
        );
    }

    /**
     * @return Generator<Core\Models\License\SpdxLicense|Core\Models\License\NamedLicense|Core\Models\License\LicenseExpression>
     */
    private function createLicensesFromPackage(Package\CompletePackageInterface $package): Generator
    {
        foreach ($package->getLicense() as $license) {
            yield $this->licenseFactory
                ->makeFromString($license)
                ->setAcknowledgement(Core\Enums\LicenseAcknowledgement::Declared)
            ;
        }
    }

    /**
     * @return Generator<Core\Models\ExternalReference>
     */
    private function createExternalReferencesFromPackage(Package\PackageInterface $package): Generator
    {
        foreach ($package->getDistUrls() as $distUrl) {
            $ref = new Core\Models\ExternalReference(Core\Enums\ExternalReferenceType::Distribution, $distUrl);
            $ref->getHashes()->set(Core\Enums\HashAlgorithm::SHA_1, $package->getDistSha1Checksum());
            $ref->setComment($package->getDistReference());

            yield $ref;
        }

        foreach ($package->getSourceUrls() as $sourceUrl) {
            $ref = new Core\Models\ExternalReference(Core\Enums\ExternalReferenceType::VCS, $sourceUrl);
            $ref->setComment($package->getSourceReference());

            yield $ref;
        }

        if ($package instanceof Package\CompletePackageInterface && ($homepage = $package->getHomepage()) !== null) {
            yield new Core\Models\ExternalReference(Core\Enums\ExternalReferenceType::Website, $homepage);
        }
    }

    private function createPurlFromComponent(Core\Models\Component $component): PackageUrl
    {
        $purl = new PackageUrl('composer', $component->getName());
        $purl->setNamespace($component->getGroup());
        $purl->setVersion($component->getVersion());

        return $purl;
    }

    /**
     * @return Generator<Core\Models\Tool>
     */
    private function createTools(): Generator
    {
        $composerTool = new Core\Models\Tool();
        $composerTool->setName('composer');
        $composerTool->setVersion(Composer::getVersion());

        yield $composerTool;

        $packageNames = [
            'cyclonedx/cyclonedx-library',
            'eliashaeussler/typo3-vendor-bundler',
        ];

        foreach ($packageNames as $packageName) {
            [$vendor, $name] = $this->splitPackageName($packageName);
            $package = $this->findToolPackage($packageName);

            if (null === $package) {
                continue;
            }

            $tool = new Core\Models\Tool();
            $tool->setVendor($vendor);
            $tool->setName($name);
            $tool->setVersion($package->getPrettyVersion());
            $tool->getExternalReferences()->addItems(
                ...$this->createExternalReferencesFromPackage($package),
            );

            yield $tool;
        }
    }

    private function findToolPackage(string $packageName): ?Package\PackageInterface
    {
        $io = new IO\NullIO();

        $localComposerJson = Filesystem\Path::join($this->rootPath, 'composer.json');
        $localComposer = Factory::create($io, $localComposerJson);
        $localPackage = $localComposer->getRepositoryManager()->findPackage(
            $packageName,
            new Semver\Constraint\MatchAllConstraint(),
        );

        if (null !== $localPackage) {
            return $localPackage;
        }

        return Factory::createGlobal($io)?->getRepositoryManager()->findPackage(
            $packageName,
            new Semver\Constraint\MatchAllConstraint(),
        );
    }

    /**
     * @param array<string, Core\Models\Component> $components
     */
    private function applyDependencyTree(
        Package\PackageInterface $package,
        Repository\LockArrayRepository $repository,
        array $components,
    ): void {
        $component = $components[$package->getName()] ?? null;

        if (null === $component) {
            return;
        }

        foreach ($package->getRequires() as $requirement) {
            $requiredPackage = $repository->findPackage($requirement->getTarget(), $requirement->getConstraint());

            if (null === $requiredPackage) {
                continue;
            }

            $dependency = $components[$requiredPackage->getName()] ?? null;

            if (null !== $dependency) {
                $component->getDependencies()->addItems($dependency->getBomRef());
            }
        }
    }

    /**
     * @return array{string|null, string}
     */
    private function splitPackageName(string $packageName): array
    {
        if (!str_contains($packageName, '/')) {
            return [null, $packageName];
        }

        /* @phpstan-ignore return.type */
        return explode('/', $packageName, 2);
    }
}
