<?php

$loader = require __DIR__.'/../vendor/autoload.php';

// Unregister classmap entries for App\ and Tests\ so worktree files always take precedence
$ref = new ReflectionProperty($loader, 'classMap');
$classMap = $ref->getValue($loader);
foreach ($classMap as $class => $file) {
    if (str_starts_with($class, 'App\\') || str_starts_with($class, 'Tests\\')) {
        unset($classMap[$class]);
    }
}
$ref->setValue($loader, $classMap);

// Prepend worktree paths
$loader->addPsr4('App\\', [dirname(__DIR__).'/app'], true);
$loader->addPsr4('Tests\\', [dirname(__DIR__).'/tests'], true);

return $loader;
