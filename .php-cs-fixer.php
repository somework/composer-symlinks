<?php
$finder = \PhpCsFixer\Finder::create();
$finder
    ->in([
        __DIR__ . '/src',
    ])
    ->files()
    ->name('*.php');
return (new PhpCsFixer\Config())
    ->setUsingCache(true)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2'                                     => true,
        'psr_autoloading'                            => true,
        'align_multiline_comment'                   => true,
        'dir_constant'                              => true,
        'linebreak_after_opening_tag'               => true,
        'modernize_types_casting'                   => true,
        'multiline_whitespace_before_semicolons'     => true,
        'no_null_property_initialization'           => true,
        'no_php4_constructor'                       => true,
        'no_superfluous_elseif'                     => true,
        'no_unneeded_final_method'                  => true,
        'no_unneeded_curly_braces'                  => true,
        'no_unused_imports'                         => true,
        'no_useless_else'                           => true,
        'ordered_imports'                           => true,
        'phpdoc_add_missing_param_annotation'       => true,
        'php_unit_construct'                        => true,
        'phpdoc_order'                              => true,
        'pow_to_exponentiation'                     => true,
        'random_api_migration'                      => true,
        'phpdoc_types_order'                        => true,
        'single_quote'                              => true,
        'standardize_not_equals'                    => true,
        'trailing_comma_in_multiline'               => ['elements' => ['arrays']],
        'include'                                   => true,
        'array_syntax'                              => [
            'syntax' => 'short',
        ],
    ])
    ->setFinder($finder);
