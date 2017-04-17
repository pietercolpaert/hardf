<?php
include_once(__DIR__ . '/../vendor/autoload.php');
use pietercolpaert\hardf\TriGParser;

if (sizeof($argv) !== 2) {
    echo 'Usage: parser-perf.php filename';
    exit;
}

$filename = $argv[1];
$base = 'file://' . $filename;

$TEST = microtime(true);

$count = 0;
$parser = new TriGParser([ "documentIRI" => $base ]);
$callback = function ($error, $triple) use (&$count, $TEST, $filename) {
    if ($triple) {
        $count++;
    }
    else {
        echo '- Parsing file ' . $filename . ': ' . (microtime(true) - $TEST) . "s\n";
        echo '* Triples parsed: ' . $count . "\n";
        echo '* Memory usage: ' .  (memory_get_usage() / 1024 / 1024) . "MB\n";
    }
};

$handle = fopen($filename, "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        $parser->parseChunk($line, $callback);
    }
    $parser->end($callback);
    fclose($handle);
} else {
    // error opening the file.
    echo "File not found " . $filename;
}
