<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

// Nota: rector/rector-symfony ainda não tem release compatível com rector/rector ^2.6,
// por isso ficamos com os sets genéricos (PHP + qualidade de código + dead code).
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSkip([
        __DIR__ . '/src/Kernel.php',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
    ])
    ->withImportNames(removeUnusedImports: true)
;
