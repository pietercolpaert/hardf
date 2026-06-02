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
    echo "File not found or not readable: ".$filename."\n";
    exit(1);
}

$TEST = microtime(true);

$count = 0;
$parser = new TriGParser(['documentIRI' => $base], function ($error, $triple) use (&$count, $TEST, $filename) {
    if ($error) {
        echo '- Parsing file '.$filename.' failed after '.(microtime(true) - $TEST)."s\n";
        echo '* Error: '.$error->getMessage()."\n";
        exit(1);
    } elseif ($triple) {
        ++$count;
    } else {
        echo '- Parsing file '.$filename.': '.(microtime(true) - $TEST)."s\n";
        echo '* Triples parsed: '.$count."\n";
        echo '* Memory usage: '.(memory_get_usage() / 1024 / 1024)."MB\n";
    }
});

$handle = fopen($filename, 'r');
if (false === $handle) {
    echo "Could not open file: ".$filename."\n";
    exit(1);
}

while (false !== ($line = fgets($handle, 4096))) {
    $parser->parseChunk($line);
}
$parser->end();
fclose($handle);
