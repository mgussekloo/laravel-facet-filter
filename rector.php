<?php

declare(strict_types=1);

use RectorLaravel\Set\LaravelLevelSetList;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src'
    ]);

    $rectorConfig->sets([
        LaravelLevelSetList::UP_TO_LARAVEL_90,
		LevelSetList::UP_TO_PHP_81
    ]);

    // register a single rule
    $rectorConfig->rule(\Rector\TypeDeclaration\Rector\Closure\AddClosureReturnTypeRector::class);
	$rectorConfig->rule(\RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector::class);
	// $rectorConfig->rule(\RectorLaravel\Rector\ClassMethod\MigrateToSimplifiedAttributeRector::class);
	// $rectorConfig->rule(\RectorLaravel\Rector\ClassMethod\OptionalToNullsafeOperatorRector::class);


    // define sets of rules
       // $rectorConfig->sets([
       //     LevelSetList::UP_TO_PHP_81
       // ]);
};
