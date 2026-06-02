<?php
include_once(__DIR__.'/../vendor/autoload.php');
use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\TriGWriter;

echo "--- First, simple implementation ---\n";
$parser = new TriGParser([]);
$writer = new TriGWriter(['format' => 'trig']);
$triples = $parser->parse("(<x>) <a> <b>. <b> <c> \"\"\"\n\"\"\". <claim> <about> <<(<s> <p> <o>)>>.");
$writer->addTriples($triples);
echo $writer->end();

//Or, option 2, the streaming version
echo "--- Second streaming implementation with callbacks ---\n";
$parser = new TriGParser();
$writer = new TriGWriter(['format' => 'trig']);
$parser->parse('@prefix ex: <http://ex.org/> . <http://A> <https://B> <http://C> <http://G> . <A2> <https://B2> <http://C2> <http://G3> . ex:s ex:p ex:o {| ex:certainty "0.8" |}. ', function ($e, $triple) use (&$writer) {
    if ($e) {
        echo 'Error occurred: '.$e->getMessage()."\n";
    } elseif ($triple) {
        $writer->addTriple($triple);
    } else {
        echo $writer->end();
    }
}, function ($prefix, $iri) use (&$writer) {
    $writer->addPrefix($prefix, $iri);
});
