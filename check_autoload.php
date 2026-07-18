<?php
require __DIR__ . '/vendor/autoload.php';

$class = 'chillerlan\QRCode\QRCode';
echo 'Class loaded: ' . (class_exists($class) ? 'YES' : 'NO') . PHP_EOL;

// Check PSR-4 autoloader
$autoloaders = spl_autoload_functions();
foreach ($autoloaders as $loader) {
    if (is_array($loader) && $loader[0] instanceof Composer\Autoload\ClassLoader) {
        $prefixes = $loader->getPrefixesPsr4();
        echo 'chillerlan prefix in PSR4: ' . (isset($prefixes['chillerlan\\QRCode\\']) ? 'YES' : 'NO') . PHP_EOL;
        if (isset($prefixes['chillerlan\\QRCode\\'])) {
            echo 'Paths: ' . implode(', ', $prefixes['chillerlan\\QRCode\\']) . PHP_EOL;
        }
        $classmap = $loader->getClassMap();
        echo 'chillerlan in classmap: ' . (isset($classmap[$class]) ? 'YES' : 'NO') . PHP_EOL;
        break;
    }
}

// Test direct lookup
$file = $loader->findFile($class);
echo 'Find file result: ' . ($file ?: 'NOT FOUND') . PHP_EOL;
