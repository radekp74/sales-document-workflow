<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/migrations',
    ])
    ->append([
        __FILE__,
    ]);

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        // PER-CS 2.0 wymaga spacji wokół konkatenacji; @Symfony to nadpisuje.
        // Utrzymujemy wariant PER-CS, zgodny ze stylem dostarczonego baseline'u.
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        // Baseline i nowy kod używają naturalnej kolejności porównań.
        'yoda_style' => false,
    ])
    ->setFinder($finder);
