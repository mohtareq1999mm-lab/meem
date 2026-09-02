<?php
require 'vendor/autoload.php';
$ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $ss->getActiveSheet();
$sheet->fromArray(['sku','name_en'], null, 'A1');
$tmp = tempnam(sys_get_temp_dir(), 'test');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($tmp);
echo 'saved to '.$tmp . PHP_EOL;
echo filesize($tmp) . PHP_EOL;
try {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
    $reader->setReadDataOnly(true);
    $ss2 = $reader->load($tmp);
    echo 'loaded sheets: '.implode(',', $ss2->getSheetNames()).PHP_EOL;
    echo 'highest row: '.$ss2->getActiveSheet()->getHighestDataRow().PHP_EOL;
} catch (Exception $e) { echo 'error: '.$e->getMessage().PHP_EOL; }
unlink($tmp);
