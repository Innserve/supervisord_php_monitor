<?php

$finder = PhpCsFixer\Finder::create()
    // Adjust scope as needed (e.g., ->in([__DIR__.'/src', __DIR__.'/web/modules/custom']))
    ->in(__DIR__)
    ->exclude([
        'vendor',
        'node_modules',
        'var',
        'cache',
        'build',
        'dist',
        '.git',
        'coverage',
        'reports',
        'tmp',
    ])
    // Include common Drupal/PHP file types you’re using
    ->name('*.php')
    ->name('*.inc')
    ->name('*.module')
    ->name('*.install')
    ->name('*.theme')
    ->ignoreVCSIgnored(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,

        // --- Blank lines / spacing (kills "double returns") ---
        // Collapse multiple blank lines to a single one
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra', 'use',
                'return', 'throw',
                'switch', 'case', 'default',
                'curly_brace_block', 'parenthesis_brace_block', 'square_brace_block',
            ],
        ],
        // Ensure blank lines are actually empty (no stray spaces)
        'no_whitespace_in_blank_line' => true,
        // Exactly one blank line between class members/methods
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'property' => 'one',
                'method' => 'one',
                'trait_import' => 'one',
            ],
        ],

        // --- Imports / namespaces ---
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
        ],
        'no_unused_imports' => true,
        'single_import_per_statement' => true,
        'blank_line_between_import_groups' => true,

        // --- Whitespace & punctuation consistency ---
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays'],
        ],
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'single_blank_line_at_eof' => true,

        // --- Misc safe cleanups ---
        'concat_space' => ['spacing' => 'one'],
        'elseif' => true,
        'function_typehint_space' => true,
        'no_spaces_after_function_name' => true,
        'no_spaces_inside_parenthesis' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'include' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
