<?php

namespace Mediatis\CodingStandards\Php;

use Exception;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Rector\ValueObject\PhpVersion;

class RectorSetup
{
    protected static int $phpVersion;

    /**
     * @return array<string>
     */
    protected static function sets(): array
    {
        return [
            match (static::$phpVersion) {
                PhpVersion::PHP_82 => LevelSetList::UP_TO_PHP_82,
                PhpVersion::PHP_83 => LevelSetList::UP_TO_PHP_83,
                default => throw new Exception(sprintf('unkonwn PHP version "%s"', static::$phpVersion)),
            },
            SetList::CODING_STYLE,
            SetList::CODE_QUALITY,
            SetList::DEAD_CODE,
        ];
    }

    /**
     * @return string[]
     */
    protected static function paths(string $packagePath): array
    {
        return [
            $packagePath . '/src',
            $packagePath . '/tests',
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    protected static function skip(string $packagePath): array
    {
        return [
            RemoveNonExistingVarAnnotationRector::class, // conflicts with phpstan
            RemoveUselessReturnTagRector::class, // conflicts with phpstan
            CatchExceptionNameMatchingTypeRector::class,
        ];
    }

    public static function setup(RectorConfig $rectorConfig, string $packagePath, int $phpVersion = PhpVersion::PHP_82): void
    {
        static::$phpVersion = $phpVersion;

        $rectorConfig->paths(static::paths($packagePath));

        $rectorConfig->importNames(true, true);

        $rectorConfig->sets(static::sets());

        $rectorConfig->skip(static::skip($packagePath));

        $rectorConfig->rules([TypedPropertyFromStrictConstructorRector::class]);
    }
}
