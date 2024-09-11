<?php

namespace Mediatis\CodingStandards\Php;

use Exception;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassConst\VarConstantCommentRector;
use Rector\CodingStyle\Rector\ClassMethod\UnSpreadOperatorRector;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php74\Rector\LNumber\AddLiteralSeparatorToNumberRector;
use Rector\Php81\Rector\ClassConst\FinalizePublicClassConstantRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

class RectorSetup
{
    protected static int $phpVersion = PhpVersion::PHP_81;

    /**
     * @return array<string>
     */
    protected static function sets(): array
    {
        $sets = [
            SetList::CODING_STYLE,
            SetList::CODE_QUALITY,
            SetList::DEAD_CODE,
        ];
        array_push($sets, ...[
            match (static::$phpVersion) {
                PhpVersion::PHP_81 => SetList::PHP_81,
                PhpVersion::PHP_82 => SetList::PHP_82,
                PhpVersion::PHP_83 => SetList::PHP_83,
                default => throw new Exception(sprintf('unkonwn php version "%s"', static::$phpVersion)),
            },
        ]);

        return $sets;
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
            ClosureToArrowFunctionRector::class,
            FinalizePublicClassConstantRector::class,
            RemoveNonExistingVarAnnotationRector::class, // conflicts with phpstan
            RemoveUselessReturnTagRector::class, // conflicts with phpstan
            CatchExceptionNameMatchingTypeRector::class,
            AddLiteralSeparatorToNumberRector::class,
            UnSpreadOperatorRector::class, // breaks code, removed in rector 0.18
            VarConstantCommentRector::class, // conflicts with other standards (array<string>), removed in latest rector
        ];
    }

    public static function setup(RectorConfig $rectorConfig, string $packagePath): void
    {

        $rectorConfig->paths(static::paths($packagePath));

        $rectorConfig->importNames(true, true);

        $rectorConfig->sets(static::sets());

        $rectorConfig->skip(static::skip($packagePath));
    }
}
