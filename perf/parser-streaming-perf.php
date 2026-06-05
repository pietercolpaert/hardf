<?php

include_once __DIR__.'/../vendor/autoload.php';
use pietercolpaert\hardf\TriGParser;

if (2 !== count($argv)) {
    echo "Usage: parser-streaming-perf.php filename\n";
    exit;
}

$filename = $argv[1];
$base = 'file://'.$filename;

if (!is_readable($filename)) {
    echo 'File not found or not readable: '.$filename."\n";
    exit(1);
}

$TEST = microtime(true);

$count = 0;
$parser = new TriGParser(['documentIRI' => $base]);

$handle = fopen($filename, 'r');
if (false === $handle) {
    echo 'Could not open file: '.$filename."\n";
    exit(1);
}

try {
    foreach ($parser->parseStream($handle, $base) as $quad) {
        ++$count;
    }
} catch (Throwable $e) {
    echo '- Parsing file '.$filename.' failed after '.(microtime(true) - $TEST)."s\n";
    echo '* Error: '.$e->getMessage()."\n";
    fclose($handle);
    exit(1);
}

echo '- Parsing file '.$filename.': '.(microtime(true) - $TEST)."s\n";
echo '* Triples parsed: '.$count."\n";
echo '* Memory usage: '.(memory_get_usage() / 1024 / 1024)."MB\n";
fclose($handle);
