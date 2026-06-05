#!/usr/bin/php
<?php

declare(strict_types=1);

/** Validates TriG, Turtle, N3, N-QUADS or N-TRIPLES input */
include_once __DIR__.'/../vendor/autoload.php';
use pietercolpaert\hardf\TriGParserIterator;

$format = 'trig';
if (isset($argv[1])) {
    $format = $argv[1];
}
$errored = false;
$tripleCount = 0;
try {
    $parser = new TriGParserIterator(['format' => $format]);
    foreach ($parser->parseStream(\STDIN) as $quad) {
        ++$tripleCount;
    }
} catch (Exception $e) {
    echo $e->getMessage()."\n";
    $errored = true;
}
if (!$errored) {
    echo 'Parsed '.$tripleCount." triples successfully.\n";
}
