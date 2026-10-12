<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = new Finder()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php');

return new Config()
    ->setRiskyAllowed(true)
    ->setRules([
        // Coding standard
        '@PER-CS3x0' => true,

        // PHP 8.5
        '@PHP8x5Migration' => true,

        // Strict typing
        'declare_strict_types' => true,

        // Imports
        'no_unused_imports' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => [
                'class',
                'function',
                'const',
            ],
        ],
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,

        // Classes
        'final_class' => true,
        'visibility_required' => true,
        'ordered_class_elements' => true,
        'single_trait_insert_per_statement' => true,

        // Code style
        'array_syntax' => [
            'syntax' => 'short',
        ],
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'no_superfluous_phpdoc_tags' => false,
        'no_empty_phpdoc' => true,
        'no_useless_return' => false,
        'simplified_if_return' => true,
        'use_arrow_functions' => true,
        'static_lambda' => true,
        'strict_param' => true,
        'no_useless_else' => true,
        'ordered_types' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'alpha',
        ],
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'alpha',
        ],
        'strict_comparison' => true,
        'void_return' => [
            'fix_lambda' => false,
        ],

        // PHPUnit
        'php_unit_attributes' => true,
        'php_unit_method_casing' => [
            'case' => 'camel_case',
        ],
        'php_unit_test_case_static_method_calls' => [
            'call_type' => 'self',
        ],
        'php_unit_data_provider_static' => true,
        'php_unit_data_provider_return_type' => true,
    ])
    ->setFinder($finder);
