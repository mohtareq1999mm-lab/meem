<?php
$file = __DIR__ . '/vendor/composer/autoload_static.php';
$content = file_get_contents($file);

if (strpos($content, 'chillerlan') !== false) {
    echo "ALREADY EXISTS\n";
    exit;
}

// Add chillerlan to $prefixLengthsPsr4 (already done in previous fix)
// Now add to $prefixDirsPsr4
$dirsSearch = "public static \$prefixDirsPsr4 = array (";
$dirPos = strpos($content, $dirsSearch);
if ($dirPos === false) {
    echo "Could not find prefixDirsPsr4\n";
    exit;
}

$dirInsertPos = $dirPos + strlen($dirsSearch);
$dirEntry = "
        'chillerlan\\QRCode\\' => 
        array (
            0 => __DIR__ . '/../..' . '/chillerlan/php-qrcode/src',
        ),
";
$content = substr_replace($content, $dirEntry, $dirInsertPos, 0);

file_put_contents($file, $content);
echo "ADDED prefixDirsPsr4 entry\n";
echo "Done\n";
