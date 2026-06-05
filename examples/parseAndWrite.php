<?php
declare(strict_types=1);

include_once __DIR__.'/../vendor/autoload.php';

use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\TriGParserIterator;
use pietercolpaert\hardf\TriGWriter;

echo "--- First, simple implementation ---\n";
$parser = new TriGParser([]);
$writer = new TriGWriter(['format' => 'trig']);
foreach ($parser->parse("(<x>) <a> <b>. <b> <c> \"\"\"\n\"\"\". <claim> <about> <<(<s> <p> <o>)>>.") as $quad) {
    $writer->addQuad($quad);
}
echo $writer->end();

// Or, option 2, the streaming version
echo "--- Second streaming implementation with the iterator ---\n";
$parser = new TriGParserIterator([], function (string $prefix, string $iri) use (&$writer): void {
    $writer->addPrefix($prefix, $iri);
});
$writer = new TriGWriter(['format' => 'trig']);
foreach ($parser->parse('@prefix ex: <http://ex.org/> . <http://A> <https://B> <http://C> <http://G> . <A2> <https://B2> <http://C2> <http://G3> . ex:s ex:p ex:o {| ex:certainty "0.8" |}. ') as $quad) {
    $writer->addQuad($quad);
}
echo $writer->end();
