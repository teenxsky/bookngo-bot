<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(['src', 'tests'])
    ->exclude(['vendor', 'var', 'bin'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'                 => true,
        'no_unused_imports'      => true,
        'ordered_imports'        => ['sort_algorithm' => 'alpha'],
        'no_extra_blank_lines'   => true,
        'line_ending'            => true,
        'single_quote'           => true,
        'strict_param'           => true,
        'binary_operator_spaces' => [
            'default'   => 'align_single_space_minimal',
            'operators' => [
                '='   => 'align_single_space_minimal',
                '=>'  => 'align_single_space_minimal',
                '??=' => 'align_single_space_minimal',
            ],
        ],
        'declare_strict_types'    => true,
        'global_namespace_import' => [
            'import_classes'   => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
