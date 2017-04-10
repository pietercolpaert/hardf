<?php
/** Validates a TriG, Turtle, N3, NQUADS or NTRIPLES file */
include_once(__DIR__ . '/../vendor/autoload.php');
use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\TriGWriter;

$parser = new TriGParser(["format" => "N3"]);
$errored = false;
$tripleCount = 0;
//TODO: change  this to STDIO
$wrong = "<http://A> <https://B> <http://C> <http://G> . <A2> <https://B2> <http://C2> <http://G3> . <E> <D> \"aa\"^^foo:bar . <a> <b> .";
$right = "<http://A> <https://B> <http://C> <http://G> . <A2> <https://B2> <http://C2> <http://G3> . ";
$parser->parse($wrong, function ($e, $triple) use (&$errored, &$tripleCount) {
    if (!$e && $triple) {
        $tripleCount ++;
    } else if ($e) {
        $errored = true;
        echo $e->getMessage() . "\n";
    } else if (!$triple && !$errored) {
        echo "Parsed " . $tripleCount . " triples successfully.\n";
    }
});
