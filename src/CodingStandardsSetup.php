<?php

namespace Mediatis\CodingStandards;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Exception;
use JsonException;
use Symfony\Component\Yaml\Yaml;

class CodingStandardsSetup
{
    /**
     * @param array<string> $requiredFolderPaths
     * @param array<string,array{packageKeys:array<string>,versions:array<float>}> $supportedPackageVersions
     */
    public function __construct(
        protected string $targetPackageDirectory,
        protected string $codingStandardsPackageDirectory,
        protected array $requiredFolderPaths,
        protected string $examplePackageDirectory,
        protected array $supportedPackageVersions,
    ) {
    }

    /**
     * @param array<float> $allowedVersions
     *
     * @return array<float>
     */
    protected function extractVersions(string $constraint, array $allowedVersions): array
    {
        $versions = [];
        $versionParser = new VersionParser();
        $constraints = $versionParser->parseConstraints($constraint);
        $semVer = new Semver();
        foreach ($allowedVersions as $allowedVersion) {
            if ($semVer::satisfies((string)$allowedVersion, $constraints->getPrettyString())) {
                $versions[] = $allowedVersion;
            }
        }

        return $versions;
    }

    protected function updateFolderStructure(string $filePath): void
    {
        if (!str_starts_with($filePath, $this->targetPackageDirectory)) {
            throw new Exception(sprintf('file path "%s" does not seem to be within the package directory.', $filePath));
        }

        $pathParts = explode('/', $filePath);
        array_pop($pathParts);
        $folderPath = implode('/', $pathParts);
        if (!is_dir($folderPath)) {
            mkdir($folderPath, recursive: true);
        }
    }

    /**
     * @return float[]
     *
     * @throws JsonException
     */
    protected function getDependencyVersionConstraintsFromComposerData(string $package, string $type = 'full', string $outputType = 'float'): array
    {
        $allowedVersions = $this->supportedPackageVersions[$package]['versions'];
        $versions = [];
        foreach ($this->supportedPackageVersions[$package]['packageKeys'] as $key) {
            $composerFilePath = $this->targetPackageDirectory . '/composer.json';
            $composerFileContents = file_get_contents($composerFilePath);
            if ($composerFileContents === false) {
                throw new Exception(sprintf('File "%s" not found!', $composerFilePath));
            }

            $composerFileContentsAsArray = json_decode($composerFileContents, associative: true, flags: JSON_THROW_ON_ERROR);
            if (isset($composerFileContentsAsArray['require'][$key])) {
                if ($type === 'major') {
                    $versions = $this->extractVersions($composerFileContentsAsArray['require'][$key], $allowedVersions);
                    foreach ($versions as $key => $number) {
                        $versions[$key] = $outputType === 'string' ? (string)(int)$number : (int)$number;
                    }
                } else {
                    $versions = $this->extractVersions($composerFileContentsAsArray['require'][$key], $allowedVersions);
                    foreach ($versions as $key => $number) {
                        $versions[$key] = $outputType === 'string' ? (string)$number : $number;
                    }
                }
            }

            if ($versions !== []) {
                break;
            }
        }

        if ($versions === []) {
            throw new Exception('Package version mismatch detected. Supported ' . $package . ' versions are: ' . implode(', ', $allowedVersions));
        }

        return $versions;
    }

    /**
     * @return string
     *
     * @throws JsonException
     */
    protected function getPackageNameFromComposerData(): string
    {
        $composerFilePath = $this->targetPackageDirectory . '/composer.json';
        $composerFileContents = file_get_contents($composerFilePath);
        if ($composerFileContents === false) {
            throw new Exception(sprintf('File "%s" not found!', $composerFilePath));
        }

        $composerFileContentsAsArray = json_decode($composerFileContents, associative: true, flags: JSON_THROW_ON_ERROR);
        $packageNameWithVendorName = explode('/', (string)$composerFileContentsAsArray['name']);
        if (!isset($packageNameWithVendorName[1]) || $packageNameWithVendorName[1] === '') {
            throw new Exception('parameter "name" needs to exist in composer.json and contain a vendor-name/package-name structure.');
        }

        return $packageNameWithVendorName[1];
    }

    /**
     * @param array<string,mixed> $sourceData
     * @param array<string,mixed> $targetData
     * @param array<string,mixed> $config
     */
    protected function updateDataComposerJson(array $sourceData, array &$targetData, array $config): void
    {
        foreach ($config as $key => $merge) {
            if (!isset($sourceData[$key])) {
                throw new Exception(sprintf('Key "%s" not found in source data!', $key));
            }

            if ($merge && isset($targetData[$key]) && is_array($targetData[$key])) {
                foreach ($sourceData[$key] as $configKey => $configValue) {
                    if (array_key_exists($configKey, $targetData[$key])) {
                        unset($targetData[$key][$configKey]);
                    }

                    $targetData[$key][$configKey] = $configValue;
                }
            } else {
                $targetData[$key] = $sourceData[$key];
            }
        }
    }

    /**
     * @param array<string,mixed> $config
     *
     * @throws JsonException
     */
    protected function updateFileContentsComposerJson(string $sourceContents, string $targetContents, array $config): string
    {
        $sourceData = json_decode($sourceContents, associative: true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($sourceData)) {
            throw new Exception('Composer data seems to be empty.');
        }

        $targetData = json_decode($targetContents, associative: true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($targetData)) {
            $targetData = [];
        }

        $this->updateDataComposerJson($sourceData, $targetData, $config);

        return json_encode($targetData, flags: JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    /**
     * @param array<string,mixed> $config
     */
    protected function updateFileContentsTxt(string $sourceContents, string $targetContents, array $config): string
    {
        foreach ($config as $from => $to) {
            $sourceContents = str_replace($from, $to, $sourceContents);
        }

        return $sourceContents;
    }

    /**
     * @param array<mixed> $original Original array. It will be *modified* by this method and contains the result afterwards!
     * @param array<mixed> $overrule Overrule array, overruling the original array
     */
    protected function mergeRecursiveWithOverrule(array &$original, array $overrule): void
    {
        foreach (array_keys($overrule) as $key) {
            if (isset($original[$key]) && is_array($original[$key])) {
                if (is_array($overrule[$key])) {
                    $this->mergeRecursiveWithOverrule($original[$key], $overrule[$key]);
                }
            } else {
                $original[$key] = $overrule[$key];
            }
        }

        reset($original);
    }

    /**
     * @param array<string,mixed> $config
     *
     * @throws Exception
     */
    protected function updateFileContentsYaml(string $sourceContents, string $targetContents, array $config): string
    {
        $sourceData = Yaml::parse($sourceContents);
        $targetData = Yaml::parse($targetContents);

        if (!is_array($targetData)) {
            $targetData = [];
        }

        $this->mergeRecursiveWithOverrule($sourceData, $config);
        $this->mergeRecursiveWithOverrule($targetData, $sourceData);

        return Yaml::dump($targetData, 99);
    }

    /**
     * @param array<mixed> $config
     *
     * @throws JsonException
     */
    protected function updateFileContents(string $sourceContents, string $targetContents, string $filePath, array $config): string
    {
        return match ($filePath) {
            '.ddev/config.yaml' => $this->updateFileContentsYaml($sourceContents, $targetContents, $config),
            'composer.json' => $this->updateFileContentsComposerJson($sourceContents, $targetContents, $config),
            '.github/workflows/ci.yml' => $this->updateFileContentsYaml($sourceContents, $targetContents, $config),
            '.gitlab-ci.yml' => $this->updateFileContentsYaml($sourceContents, $targetContents, $config),
            'rector.php' => $this->updateFileContentsTxt($sourceContents, $targetContents, $config),
            default => throw new Exception(sprintf('No information how to process "%s" found!', $filePath)),
        };
    }

    protected function resetFile(string $targetFilePath): void
    {
        $targetPath = $this->getTargetPath($targetFilePath);
        if (file_exists($targetPath)) {
            // nosemgrep
            unlink($targetPath);
        }
    }

    /**
     * @param array<mixed>|null $config
     *
     * @throws JsonException
     */
    protected function updateFile(string $filePath, ?string $targetFilePath = null, ?array $config = null, bool $keepExistingFile = false): void
    {
        $sourcePath = $this->getSourcePath($filePath);
        $targetPath = $this->getTargetPath($targetFilePath ?? $filePath);
        if (file_exists($targetPath) && $keepExistingFile) {
            return;
        }

        $sourceContents = file_exists($sourcePath) ? file_get_contents($sourcePath) : '';
        if ($sourceContents === false) {
            throw new Exception(sprintf('Source file "%s" not found!', $sourcePath));
        }

        if ($config !== null) {
            $targetContents = file_exists($targetPath) ? file_get_contents($targetPath) : '';
            if ($targetContents === false) {
                $targetContents = '';
            }

            $targetContents = $this->updateFileContents($sourceContents, $targetContents, $filePath, $config);
        } else {
            $targetContents = $sourceContents;
        }

        $this->updateFolderStructure($targetPath);
        file_put_contents($targetPath, $targetContents);
    }

    protected function getSourcePath(string $filePath): string
    {
        return $this->codingStandardsPackageDirectory . '/' . $this->examplePackageDirectory . '/' . $filePath;
    }

    protected function getTargetPath(string $filePath): string
    {
        return $this->targetPackageDirectory . '/' . $filePath;
    }

    protected function setupCsFixerConfig(): void
    {
        $this->updateFile('.php-cs-fixer.php');
    }

    protected function setupRectorConfig(): void
    {
        $phpVersions = $this->getDependencyVersionConstraintsFromComposerData('php', '');
        $phpVersion = match ($phpVersions[0]) {
            8.2 => 'PHP_82',
            8.3 => 'PHP_83',
            default => throw new Exception('Unable to set up rector due to version mismatch. Supported PHP versions are: ' . implode(', ', $this->supportedPackageVersions['php']['versions'])),
        };
        $this->updateFile('rector.php', config: [
            'PHP_VERSION_PLACEHOLDER' => $phpVersion,
        ]);
    }

    protected function setupPhpStanConfig(): void
    {
        // It may be necessary to have your own ruleset, so we keep the existing file
        $this->updateFile('phpstan.neon', keepExistingFile: true);
    }

    protected function setupCiPipeline(): void
    {
        $matrix = [];
        foreach (array_keys($this->supportedPackageVersions) as $package) {
            $matrix[$package . '_version'] = $this->getDependencyVersionConstraintsFromComposerData($package, outputType: 'string');
        }

        $this->updateFile('.gitlab-ci.yml',
            config: [
                'code-quality' => [
                    'parallel' => [
                        'matrix' => [
                            $matrix,
                        ],
                    ],
                ],
            ]
        );

        $matrix = [];
        foreach (array_keys($this->supportedPackageVersions) as $package) {
            $matrix[$package . '_version'] = $this->getDependencyVersionConstraintsFromComposerData($package);
        }

        $this->updateFile('.github/workflows/ci.yml',
            config: [
                'jobs' => [
                    'code-quality' => [
                        'strategy' => [
                            'matrix' => $matrix,
                        ],
                    ],
                ],
            ]
        );
    }

    protected function setupComposerJson(): void
    {
        $this->updateFile('composer.json',
            config: [
                'scripts' => true,
                'scripts-descriptions' => true,
            ]
        );
    }

    protected function setupRequiredFolders(): void
    {
        foreach ($this->requiredFolderPaths as $requiredFolderPath) {
            if (!is_dir($this->getTargetPath($requiredFolderPath))) {
                $this->updateFile($requiredFolderPath . '/.gitkeep');
            }
        }
    }

    public function setup(): void
    {
        $this->setupCsFixerConfig();
        $this->setupRectorConfig();
        $this->setupPhpStanConfig();
        $this->setupCIPipeline();
        $this->setupComposerJson();
        $this->setupRequiredFolders();
    }

    public function reset(): void
    {
        $this->resetFile('.php-cs-fixer.php');
        $this->resetFile('rector.php');
        $this->resetFile('phpstan.neon');
        $this->resetFile('.gitlab-ci.yml');
        $this->resetFile('.github/workflows/ci.yml');
    }
}
