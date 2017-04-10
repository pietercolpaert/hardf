<?php
include_once(__DIR__ . '/../vendor/autoload.php');
use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\TriGWriter;

echo "--- First, simple implementation ---\n";
$parser = new TriGParser();
$writer = new TriGWriter(["format"=>"trig"]);
$triples = $parser->parse("(<x>) <a> <b>.");
$writer->addTriples($triples);
echo $writer->end();

//Or, option 2, the streaming version
echo "--- Second streaming implementation with callbacks ---\n";
$parser = new TriGParser();
$writer = new TriGWriter(["format"=>"trig"]);
$error = null;
$parser->parse("<http://A> <https://B> <http://C> <http://G> . <A2> <https://B2> <http://C2> <http://G3> .", function ($e, $triple) use ($writer) {
    if (!$e && $triple)
        $writer->addTriple($triple);
    else if (!$triple)
        echo $writer->end();
    else
        echo "Error occured: " . $e;
});
