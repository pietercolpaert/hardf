<?php
/** Converts TriG, Turtle, N3, N-QUADS or N-TRIPLES input to TriG, Turtle, N-QUADS or N-TRIPLES*/
include_once(__DIR__ . '/../vendor/autoload.php');
use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\TriGWriter;
$informat = "turtle";
if (isset($argv[1]))
    $informat = $argv[1];
$parser = new TriGParser(["format" => $informat]);

$outformat = "n-triples";
if (isset($argv[2]))
    $outformat = $argv[2];

$writer = new TriGWriter(["format" => $outformat]);

$callback = function ($error, $triple) use (&$writer)
{
    if (!isset($error) && !isset($triple)) {
        echo $writer->end();
    } else if (!$error) {
        $writer->addTriple($triple);
        echo $writer->read();
    } else {
        fwrite(STDERR, $error->getMessage() . "\n");
    }
};

while ($line = fgets(STDIN)) {
    $parser->parseChunk($line, $callback);
}
$parser->end($callback);
