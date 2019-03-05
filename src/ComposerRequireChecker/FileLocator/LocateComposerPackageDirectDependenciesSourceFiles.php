<?php

namespace ComposerRequireChecker\FileLocator;

use ComposerRequireChecker\JsonLoader;
use Generator;
use function array_key_exists;
use function file_get_contents;
use function json_decode;

final class LocateComposerPackageDirectDependenciesSourceFiles
{
    public function __invoke(string $composerJsonPath): Generator
    {
        $packageDir = dirname($composerJsonPath);

        $composerJson = json_decode(file_get_contents($composerJsonPath), true);
        $configVendorDir = $composerJson['config']['vendor-dir'] ?? 'vendor';
        $vendorDirs = [];
        foreach ($composerJson['require'] ?? [] as $vendorName => $vendorRequiredVersion) {
            $vendorDirs[$vendorName] = $packageDir . '/' . $configVendorDir . '/' . $vendorName;
        };

        if (empty($vendorDirs)) {
            return;
        }

        $installedPackages = $this->getInstalledPackages($packageDir . '/' . $configVendorDir);

        foreach ($vendorDirs as $vendorName => $vendorDir) {
            if (!array_key_exists($vendorName, $installedPackages)) {
                continue;
            }

            yield from (new LocateComposerPackageSourceFiles())->__invoke($installedPackages[$vendorName], $vendorDir);
        }
    }


    /**
     * Lookup each vendor package's composer.json info from installed.json
     *
     * @param string $vendorDir
     *
     * @return array Keys are the package name and value is the composer.json as an array
     */
    private function getInstalledPackages(string $vendorDir): array
    {
        $installedData = (new JsonLoader($vendorDir . '/composer/installed.json'))->getData();

        $installedPackages = [];

        $packages = $installedData['packages'] ?? $installedData;
        foreach ($packages as $vendorJson) {
            $vendorName = $vendorJson['name'];
            $installedPackages[$vendorName] = $vendorJson;
        }

        return $installedPackages;
    }
}
