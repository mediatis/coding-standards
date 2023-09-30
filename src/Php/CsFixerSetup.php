<?php

namespace Mediatis\CodingStandards\Php;

use PhpCsFixer\Config;

class CsFixerSetup
{
    public static function getRules(): array
    {
        return [
            '@PSR12' => true,
            '@Symfony' => true,

            // modified rules
            'binary_operator_spaces' => ['operators' => ['=>' => null]],
            'cast_spaces' => ['space' => 'none'],
            'class_definition' => ['single_item_single_line' => true],
            'concat_space' => ['spacing' => 'one'],
            'global_namespace_import' => ['import_classes' => true, 'import_constants' => true, 'import_functions' => true],

            // disabled rules
            'no_superfluous_phpdoc_tags' => false, // conflicts with phpstan
            'nullable_type_declaration_for_default_null_value' => false,
            'phpdoc_align' => false,
            'phpdoc_summary' => false,
            'phpdoc_to_comment' => false, // conflicts with phpstan
            'yoda_style' => false,

            // added rules
            'no_useless_else' => true,
        ];
    }

    protected static function appendRules(Config $config, array $rules): void
    {
        $rules = array_replace_recursive($config->getRules(), $rules);
        $config->setRules($rules);
    }

    public static function setup(?Config $config = null): Config
    {
        $append = false;
        if (!$config instanceof Config) {
            $config = new Config();
            $append = true;
        }

        $config->setUsingCache(false);

        if ($append) {
            static::appendRules($config, static::getRules());
        } else {
            $config->setRules(static::getRules());
        }

        return $config;
    }
}
