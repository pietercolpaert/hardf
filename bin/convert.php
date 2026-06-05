#!/usr/bin/php
<?php

declare(strict_types=1);

/** Converts TriG, Turtle, N3, N-QUADS or N-TRIPLES input to TriG, Turtle, N-QUADS or N-TRIPLES*/
include_once __DIR__.'/../vendor/autoload.php';
use pietercolpaert\hardf\TriGParserIterator;
use pietercolpaert\hardf\TriGWriter;

$informat = 'turtle';
if (isset($argv[1])) {
    $informat = $argv[1];
}

$outformat = 'n-triples';
if (isset($argv[2])) {
    $outformat = $argv[2];
}

$writer = new TriGWriter(['format' => $outformat]);
$parser = new TriGParserIterator(['format' => $informat], function (string $prefix, string $iri) use (&$writer): void {
    $writer->addPrefix($prefix, $iri);
    echo $writer->read();
});

try {
    foreach ($parser->parseStream(\STDIN) as $quad) {
        $writer->addQuad($quad);
        echo $writer->read();
    }

    echo $writer->end();
} catch (Exception $e) {
    fwrite(\STDERR, $e->getMessage()."\n");
    exit(1);
}
