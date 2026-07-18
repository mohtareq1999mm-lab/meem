<?php
$file = __DIR__ . '/vendor/composer/autoload_static.php';
$content = file_get_contents($file);

// Add chillerlan to $prefixLengthsPsr4
$lengthsSearch = "public static \$prefixLengthsPsr4 = array (";
$lenPos = strpos($content, $lengthsSearch);
if ($lenPos !== false) {
    $lenInsertPos = $lenPos + strlen($lengthsSearch);
    $entry = "\n        'C' => 
        array (
            'chillerlan\\\\QRCode\\\\' => 18,
        ),
";
    if (strpos($content, 'chillerlan') === false) {
        $content = substr_replace($content, $entry, $lenInsertPos, 0);
        echo "Added prefixLengthsPsr4 entry\n";
    }
}

// Add chillerlan to $prefixDependencies or $prefixesPsr4
// Find $prefixesPsr4
$prefixesSearch = "'prefixesPsr4' => \n    array (";
$prefPos = strpos($content, $prefixesSearch);
if ($prefPos === false) {
    // Try different format
    $prefixesSearch = "'prefixesPsr4' => array (";
    $prefPos = strpos($content, $prefixesSearch);
}
if ($prefPos !== false) {
    $prefInsertPos = $prefPos + strlen($prefixesSearch);
    $entry = "\n        'chillerlan\\\\QRCode\\\\' => 
        array (
            0 => __DIR__ . '/../..' . '/chillerlan/php-qrcode/src',
        ),
";
    if (strpos($content, 'chillerlan') === false) {
        $content = substr_replace($content, $entry, $prefInsertPos, 0);
        echo "Added prefixesPsr4 entry\n";
    }
}

file_put_contents($file, $content);
echo "Done\n";
