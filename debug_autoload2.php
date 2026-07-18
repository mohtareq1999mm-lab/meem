<?php
require __DIR__ . '/vendor/autoload.php';

$loaders = spl_autoload_functions();
foreach ($loaders as $l) {
    if (is_array($l) && $l[0] instanceof Composer\Autoload\ClassLoader) {
        $ref = new ReflectionClass($l[0]);
        $prop = $ref->getProperty('prefixDirsPsr4');
        $prop->setAccessible(true);
        $val = $prop->getValue($l[0]);
        echo 'Has chillerlan prefix: ' . (isset($val['chillerlan\\QRCode\\']) ? 'YES' : 'NO') . "\n";
        
        $prop2 = $ref->getProperty('prefixLengthsPsr4');
        $prop2->setAccessible(true);
        $val2 = $prop2->getValue($l[0]);
        echo 'Has C key in prefixLengths: ' . (isset($val2['C']) ? 'YES' : 'NO') . "\n";
        if (isset($val2['C'])) {
            echo 'C contents: ' . json_encode($val2['C']) . "\n";
        }
    }
}

echo "\nFinal check: ";
echo class_exists('chillerlan\QRCode\QRCode') ? 'EXISTS' : 'NOT FOUND';
echo "\n";
