<?php
$file = __DIR__ . '/vendor/composer/autoload_static.php';
$content = file_get_contents($file);

$qrCodeEntry = "        'chillerlan\\\\QRCode\\\\' => 
        array (
            0 => __DIR__ . '/../..' . '/chillerlan/php-qrcode/src',
        ),
";

$search = "'prefixesPsr4' => \n    array (\n";
$pos = strpos($content, $search);
if ($pos !== false) {
    $insertPos = $pos + strlen($search);
    if (strpos($content, 'chillerlan') === false) {
        $content = substr_replace($content, "\n" . $qrCodeEntry, $insertPos, 0);
        // Also update prefixLengthsPsr4
        $lengthsSearch = "'prefixLengthsPsr4' => \n    array (\n";
        $lenPos = strpos($content, $lengthsSearch);
        if ($lenPos !== false) {
            $lenInsertPos = $lenPos + strlen($lengthsSearch);
            $lengthEntry = "        'c' => 
        array (
            'C' => 
            array (
                0 => 'chillerlan\\\\QRCode\\\\',
            ),
        ),
";
            if (strpos($content, "'C' =>") === false) {
                $content = substr_replace($content, "\n" . $lengthEntry, $lenInsertPos, 0);
            }
        }
        file_put_contents($file, $content);
        echo "ADDED to autoload_static.php\n";
    } else {
        echo "ALREADY EXISTS\n";
    }
} else {
    echo "Could not find prefixesPsr4 section\n";
}
