<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['storage', 'bootstrap/cache']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@Laravel' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => true,
    ])
    ->setFinder($finder);