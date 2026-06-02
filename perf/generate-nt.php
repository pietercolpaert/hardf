<?php

declare(strict_types=1);

include_once __DIR__.'/../vendor/autoload.php';

use pietercolpaert\hardf\TriGWriter;

if (3 !== count($argv)) {
    echo "Usage: generate-nt.php output-file triple-count\n";
    exit(1);
}

$outputFile = $argv[1];
$tripleCount = (int) $argv[2];

if ($tripleCount <= 0) {
    echo "Triple count must be a positive integer\n";
    exit(1);
}

$directory = dirname($outputFile);
if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    echo 'Could not create directory: '.$directory."\n";
    exit(1);
}

$handle = fopen($outputFile, 'wb');
if (false === $handle) {
    echo 'Could not open output file: '.$outputFile."\n";
    exit(1);
}

$writer = new TriGWriter(['format' => 'N-Triples'], function (string $chunk) use ($handle, $outputFile) {
    if (false === fwrite($handle, $chunk)) {
        fclose($handle);
        throw new RuntimeException('Could not write to output file: '.$outputFile);
    }
});

for ($index = 0; $index < $tripleCount; ++$index) {
    $subject = 'http://example.org/s'.$index;
    if (0 === $index % 17) {
        $subject = '_:s'.$index;
    }

    if (0 === $index % 13) {
        $object = '_:o'.$index;
    } elseif (0 === $index % 3) {
        $object = '"literal '.$index.'"';
    } else {
        $object = 'http://example.org/o'.$index;
    }

    $writer->addTriple(
        $subject,
        'http://example.org/p',
        $object
    );
}

$writer->end();
fclose($handle);
echo 'Generated '.$tripleCount.' triples at '.$outputFile."\n";