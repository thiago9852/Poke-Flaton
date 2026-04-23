<?php

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/config', __DIR__ . '/public'])
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
        'public/bundles',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'declare_strict_types' => false,
        'single_line_throw' => false,
        'yoda_style' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache')
;
