<?php
require __DIR__ . '/vendor/autoload.php';

$loader = null;
$autoloaders = spl_autoload_functions();
foreach ($autoloaders as $al) {
    if (is_array($al) && isset($al[0]) && $al[0] instanceof Composer\Autoload\ClassLoader) {
        $loader = $al[0];
        break;
    }
}

if (!$loader) {
    echo "No Composer ClassLoader found\n";
    // Show registered autoloaders
    foreach (spl_autoload_functions() as $func) {
        echo "Autoloader: " . (is_array($func) ? get_class($func[0]) . '::' . $func[1] : (is_string($func) ? $func : 'closure')) . "\n";
    }
    exit;
}

echo "ClassLoader found\n";
echo "PrefixLengthsPsr4 has chillerlan: " . (isset($loader->getPrefixLengthsPsr4()['C']) ? 'YES' : 'NO') . "\n";
echo "PrefixDirsPsr4 has chillerlan: " . (isset($loader->getPrefixDirsPsr4()['chillerlan\\QRCode\\']) ? 'YES' : 'NO') . "\n";

$file = $loader->findFile('chillerlan\\QRCode\\QRCode');
echo "FindFile result: " . ($file ?: 'NOT FOUND') . "\n";
