<?php

include_once __DIR__.'/../vendor/autoload.php';
use pietercolpaert\hardf\TriGParser;

if (count($argv) < 2) {
    echo "Usage: parser-streaming-perf.php filename [--format=FORMAT] [--relax]\n";
    exit(1);
}

$filename = $argv[1];
$base = 'file://'.$filename;
$format = null;
$relax = false;
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--format=')) {
        $format = substr($arg, 9);
    } elseif ('--relax' === $arg) {
        $relax = true;
    }
}

if (!is_readable($filename)) {
    echo 'File not found or not readable: '.$filename."\n";
    exit(1);
}

$TEST = microtime(true);

$count = 0;
$options = ['documentIRI' => $base];
if (null !== $format && '' !== $format) {
    $options['format'] = $format;
}
if ($relax) {
    $options['relax'] = true;
}

$parser = new TriGParser($options, function ($error, $triple) use (&$count, $TEST, $filename) {
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
    echo 'Could not open file: '.$filename."\n";
    exit(1);
}

while (false !== ($line = fgets($handle, 4096))) {
    $parser->parseChunk($line);
}
$parser->end();
fclose($handle);
