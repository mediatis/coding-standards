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
            if ($semVer::satisfies($allowedVersion, $constraints->getPrettyString())) {
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

    protected function getDependencyVersionConstraintsFromComposerData(string $package, string $type = 'full'): ?array
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
                if ($type == 'major') {
                    $versions = $this->extractVersions($composerFileContentsAsArray['require'][$key], $allowedVersions);
                    foreach ($versions as $key => $number) {
                        $versions[$key] = (int)$number;
                    }
                } else {
                    $versions = $this->extractVersions($composerFileContentsAsArray['require'][$key], $allowedVersions);
                }
            }

            if ($versions !== null && $versions !== []) {
                break;
            }
        }

        if ($versions === null || $versions === []) {
            throw new Exception('Package version mismatch detected. Supported ' . $package . ' versions are: ' . implode(', ', $this->supportedPackageVersions[$package]['versions']));
        }

        return $versions;
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

        if (!$this->checkKeysRecursive($config, $sourceData)) {
            throw new Exception('Configured key not found in source data!');
        }

        $this->mergeRecursiveWithOverrule($sourceData, $config);

        foreach (array_keys($config) as $key) {
            if ($this->checkKeysRecursive($config, $targetData)) {
                $this->mergeRecursiveWithOverrule($targetData[$key], $sourceData[$key]);
            } else {
                $targetData[$key] = $sourceData[$key];
            }
        }

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
            'composer.json' => $this->updateFileContentsComposerJson($sourceContents, $targetContents, $config),
            '.github/workflows/ci.yml' => $this->updateFileContentsYaml($sourceContents, $targetContents, $config),
            '.gitlab-ci.yml' => $this->updateFileContentsYaml($sourceContents, $targetContents, $config),
            default => throw new Exception('No information how to process "%s" found!', $filePath),
        };
    }

    protected function resetFile(string $targetFilePath): void
    {
        $targetPath = $this->getTargetPath($targetFilePath);
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
    }

    /**
     * @param array<mixed>|null $config
     *
     * @throws JsonException
     */
    protected function updateFile(string $filePath, ?string $targetFilePath = null, ?array $config = null): void
    {
        $sourcePath = $this->getSourcePath($filePath);
        $targetPath = $this->getTargetPath($targetFilePath ?? $filePath);
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
        $this->updateFile('rector.php');
    }

    protected function setupPhpStanConfig(): void
    {
        $this->updateFile('phpstan.neon');
    }

    protected function setupCiPipeline(): void
    {
        $matrix = [];
        foreach (array_keys($this->supportedPackageVersions) as $package) {
            $versionConstraint = $this->getDependencyVersionConstraintsFromComposerData($package);
            if ($versionConstraint !== null && $versionConstraint !== []) {
                $matrix[$package . '_version'] = $versionConstraint;
            } else {
                throw new Exception('Unable to set up CI workflows due to version mismatch. Supported ' . $package . ' versions are: ' . implode(', ', $this->supportedPackageVersions[$package]['versions']));
            }
        }

        $this->updateFile('.gitlab-ci.yml',
            config: [
                'stages' => true,
                'coding-standards' => [
                    'parallel' => [
                        'matrix' => [
                            $matrix,
                        ],
                    ],
                ],
            ]
        );

        $this->updateFile('.github/workflows/ci.yml',
            config: [
                'on' => true,
                'jobs' => [
                    'coding-standards' => [
                        'strategy' => [
                            'matrix' => [
                                $matrix,
                            ],
                        ],
                    ],
                ],
            ]
        );
    }

    protected function checkKeysRecursive($config, $data): bool
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                if (!isset($data[$key]) || !is_array($data[$key])) {
                    return false;
                }

                // Recursively check nested arrays
                $this->checkKeysRecursive($value, $data[$key]);
            } elseif (!isset($data[$key])) {
                return false;
            }
        }

        return true;
    }

    protected function setupComposerJson(): void
    {
        $this->updateFile('composer.json', config: [
            'scripts' => true,
            'scripts-descriptions' => true,
        ]);
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
