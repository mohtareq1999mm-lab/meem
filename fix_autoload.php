<?php
$psr4File = __DIR__ . '/vendor/composer/autoload_psr4.php';
$content = file_get_contents($psr4File);
$entry = "    'chillerlan\\QRCode\\' => array(\$vendorDir . '/chillerlan/php-qrcode/src/'),\n";
if (strpos($content, 'chillerlan') === false) {
    $content = str_replace('return array(', 'return array(' . "\n" . $entry, $content);
    file_put_contents($psr4File, $content);
    echo "ADDED PSR-4 entry\n";
} else {
    echo "ALREADY EXISTS\n";
}
$classmapFile = __DIR__ . '/vendor/composer/autoload_classmap.php';
$cmContent = file_get_contents($classmapFile);
echo 'Classmap has chillerlan: ' . (strpos($cmContent, 'chillerlan') !== false ? 'YES' : 'NO') . "\n";
