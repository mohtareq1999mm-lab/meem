<?php
// This script properly adds chillerlan to the existing 'C' entry in prefixLengthsPsr4
// and adds to prefixDirsPsr4

$file = __DIR__ . '/vendor/composer/autoload_static.php';
$content = file_get_contents($file);

// Fix 1: Find the ORIGINAL existing 'C' entry in prefixLengthsPsr4 and add chillerlan there
// The problematic entry we added earlier needs to be removed and merged into the existing 'C' entry
// Find: "'C' => \n        array (\n            'chillerlan\\QRCode\\' => 18,\n        ),"
$badEntry = "'C' => \n        array (\n            'chillerlan\\QRCode\\' => 18,\n        ),";
$badPos = strpos($content, $badEntry);
if ($badPos !== false) {
    $content = substr_replace($content, '', $badPos, strlen($badEntry));
    echo "Removed duplicate 'C' entry\n";
}

// Now find the existing 'C' entry (the one with Cviebrock etc.)
$existingCPattern = "'C' =>\n        array (\n            'Cviebrock";
$cPos = strpos($content, $existingCPattern);
if ($cPos !== false) {
    // Find the closing of this array - "        ),"
    $searchFrom = $cPos;
    $closePos = strpos($content, "        ),\n", $searchFrom);
    if ($closePos !== false) {
        // Insert chillerlan before the closing
        $chillerlanEntry = "            'chillerlan\\QRCode\\' => 18,\n";
        $content = substr_replace($content, $chillerlanEntry, $closePos, 0);
        echo "Added chillerlan to existing 'C' entry\n";
    }
}

// Fix 2: Ensure prefixDirsPsr4 has chillerlan
$dirEntry = "'chillerlan\\QRCode\\' =>";
if (strpos($content, $dirEntry) === false) {
    echo "ERROR: prefixDirsPsr4 entry missing!\n";
    // Add it
    $dirSearch = "'voku\\' =>";
    $dirPos = strpos($content, $dirSearch);
    if ($dirPos !== false) {
        $insert = "        'chillerlan\\QRCode\\' =>\n        array (\n            0 => __DIR__ . '/../..' . '/chillerlan/php-qrcode/src',\n        ),\n";
        $content = substr_replace($content, $insert, $dirPos, 0);
        echo "Added prefixDirsPsr4 entry\n";
    }
} else {
    echo "prefixDirsPsr4 entry already exists\n";
}

file_put_contents($file, $content);
echo "Done\n";
