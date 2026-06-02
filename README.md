# The hardf Turtle, N-Triples, N-Quads, TriG and N3 parser for PHP

**hardf** is a PHP 7.1+ library that lets you handle Linked Data (RDF). It offers:
 - [**Parsing**](#parsing) triples/quads from [Turtle](http://www.w3.org/TR/turtle/), [TriG](http://www.w3.org/TR/trig/), [N-Triples](http://www.w3.org/TR/n-triples/), [N-Quads](http://www.w3.org/TR/n-quads/), and [Notation3 (N3)](https://www.w3.org/TeamSubmission/n3/)
 - [**Writing**](#writing) triples/quads to [Turtle](http://www.w3.org/TR/turtle/), [TriG](http://www.w3.org/TR/trig/), [N-Triples](http://www.w3.org/TR/n-triples/), and [N-Quads](http://www.w3.org/TR/n-quads/)

Both the parser and the serializer have _streaming_ support.

_This library is a port of [N3.js](https://github.com/rdfjs/N3.js/tree/v0.10.0) to PHP_

hardf also supports RDF 1.2 features that are relevant to this representation, including triple terms, reified triples, annotation syntax, directional language literals, and `VERSION` declarations.

## Triple Representation

We use the triple representation in  PHP ported from NodeJS N3.js library. Check https://github.com/rdfjs/N3.js/tree/v0.10.0#triple-representation for more information

On purpose, we focused on performance, and not on developer friendliness.
We have thus implemented this triple representation using associative arrays rather than PHP object. Thus, the same that holds for N3.js, is now an array. E.g.:

```php
<?php
$triple = [
    'subject' =>   'http://example.org/cartoons#Tom',
    'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
    'object' =>    'http://example.org/cartoons#Cat',
    'graph' =>     'http://example.org/mycartoon', #optional
    ];
```

Encode literals as follows (similar to N3.js):

```php
'"Tom"@en-gb' // lowercase language
'"שלום"@he--rtl' // directional language
'"1"^^http://www.w3.org/2001/XMLSchema#integer' // no angular brackets <>
```

RDF 1.2 triple terms are represented as structured arrays, not as serialized strings:

```php
$tripleTerm = [
    'type' => 'TripleTerm',
    'subject' => 'http://example.org/s',
    'predicate' => 'http://example.org/p',
    'object' => 'http://example.org/o',
    'graph' => 'http://example.org/g', // optional, for quad terms
];

$triple = [
    'subject' => 'http://example.org/assertion',
    'predicate' => 'http://example.org/about',
    'object' => $tripleTerm,
    'graph' => '',
];
```

Parser callbacks and `parse()` return values can therefore contain either strings or triple-term arrays in the `object` position. `TriGWriter` accepts triple-term arrays in subject and object positions.

## Library functions

Install this library using [composer](http://getcomposer.org):

```bash
composer require pietercolpaert/hardf
```

### Writing
```php
use pietercolpaert\hardf\TriGWriter;
```

A class that should be instantiated and can write TriG or Turtle

Example use:
```php
$writer = new TriGWriter([
    "prefixes" => [
        "schema" =>"http://schema.org/",
        "dct" =>"http://purl.org/dc/terms/",
        "geo" =>"http://www.w3.org/2003/01/geo/wgs84_pos#",
        "rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
        "rdfs"=> "http://www.w3.org/2000/01/rdf-schema#"
        ],
    "format" => "n-quads" //Other possible values: n-quads, trig or turtle
]);

$writer->addPrefix("ex","http://example.org/");
$writer->addTriple("schema:Person","dct:title","\"Person\"@en","http://example.org/#test");
$writer->addTriple("schema:Person","schema:label","\"Person\"@en","http://example.org/#test");
$writer->addTriple("ex:1","dct:title","\"Person1\"@en","http://example.org/#test");
$writer->addTriple("ex:1","http://www.w3.org/1999/02/22-rdf-syntax-ns#type","schema:Person","http://example.org/#test");
$writer->addTriple("ex:2","dct:title","\"Person2\"@en","http://example.org/#test");
$writer->addTriple("schema:Person","dct:title","\"Person\"@en","http://example.org/#test2");
$writer->addTriple("ex:claim","ex:source", [
    "type" => "TripleTerm",
    "subject" => "ex:1",
    "predicate" => "dct:title",
    "object" => "\"Person1\"@en"
], "http://example.org/#test");
echo $writer->end();
```

#### All methods
```php
//The method names should speak for themselves:
$writer = new TriGWriter(["prefixes" => [ /* ... */]]);
$writer->addTriple($subject, $predicate, $object, $graph);
$writer->addTriples($triples);
$writer->addPrefix($prefix, $iri);
$writer->addPrefixes($prefixes);
//Creates blank node($predicate and/or $object are optional)
$writer->blank($predicate, $object);
//Creates rdf:list with $elements
$list = $writer->addList($elements);

//Returns the current output it is already able to create and clear the internal memory use (useful for streaming)
$out .= $writer->read();
//Alternatively, you can listen for new chunks through a callback:
$writer->setReadCallback(function ($output) { echo $output });

//Call this at the end. The return value will be the full triple output, or the rest of the output such as closing dots and brackets, unless a callback was set.
$out .= $writer->end();
//OR
$writer->end();
```

### Parsing

Next to [TriG](https://www.w3.org/TR/trig/), the TriGParser class also parses [Turtle](https://www.w3.org/TR/turtle/), [N-Triples](https://www.w3.org/TR/n-triples/), [N-Quads](https://www.w3.org/TR/n-quads/) and the [W3C Team Submission N3](https://www.w3.org/TeamSubmission/n3/)

RDF 1.2 triple terms are emitted as arrays with `type => TripleTerm`. Reified triple syntax emits an `rdf:reifies` triple whose object is such a triple term.

#### All methods

```php
$parser = new TriGParser($options, $tripleCallback, $prefixCallback);
$parser->setTripleCallback($function);
$parser->setPrefixCallback($function);
$parser->parse($input, $tripleCallback, $prefixCallback);
$parser->parseChunk($input);
$parser->end();
```

#### Basic examples for small files

Using return values and passing these to a writer:
```php
use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\TriGWriter;
$parser = new TriGParser(["format" => "n-quads"]); //also parses n-triples, n3, turtle and trig. Format is optional
$writer = new TriGWriter();
$triples = $parser->parse("<A> <B> <C> <G> .");
$writer->addTriples($triples);
echo $writer->end();
```

Using callbacks and passing these to a writer:
```php
$parser = new TriGParser();
$writer = new TriGWriter(["format"=>"trig"]);
$parser->parse("<http://A> <https://B> <http://C> <http://G> . <A2> <https://B2> <http://C2> <http://G3> .", function ($e, $triple) use ($writer) {
    if (isset($e)) {
        echo "Error occurred: ".$e->getMessage();
    } elseif (isset($triple)) {
        $writer->addTriple($triple);
        echo $writer->read(); //write out what we have so far
    } else {                         // flags the end of the file
        echo $writer->end();  //write the end
    }
});
```

Parsing RDF 1.2 triple terms:

```php
$parser = new TriGParser();
$triples = $parser->parse('<s> <p> <<(<a> <b> <c>)>>.');

// $triples[0]['object'] is:
// [
//     'type' => 'TripleTerm',
//     'subject' => 'a',
//     'predicate' => 'b',
//     'object' => 'c',
// ]
```

#### Example using chunks and keeping prefixes

When you need to parse a large file, you will need to parse only chunks and already process them. You can do that as follows:

```php
$writer = new TriGWriter(["format"=>"n-quads"]);
$tripleCallback = function ($error, $triple) use ($writer) {
    if (isset($error)) {
        throw $error;
    } elseif (isset($triple)) {
        $writer->addTriple($triple);
        echo $writer->read();
    } else {
        echo $writer->end();
    }
};
$prefixCallback = function ($prefix, $iri) use (&$writer) {
    $writer->addPrefix($prefix, $iri);
};
$parser = new TriGParser(["format" => "trig"], $tripleCallback, $prefixCallback);
$parser->parseChunk($chunk);
$parser->parseChunk($chunk);
$parser->parseChunk($chunk);
$parser->end(); //Needs to be called
```

#### Parser options

* `format` input format (case-insensitive)
  * if not provided or not matching any options below, then any [Turtle](https://www.w3.org/TR/turtle/), [TriG](https://www.w3.org/TR/trig/), [N-Triples](https://www.w3.org/TR/n-triples/) or [N-Quads](https://www.w3.org/TR/n-quads/) input can be parsed (but NOT the [N3](https://www.w3.org/TeamSubmission/n3/))
  * `turtle` - [Turtle](https://www.w3.org/TR/turtle/)
  * `trig` - [TriG](https://www.w3.org/TR/trig/)
  * contains `triple`, e.g. `triple`, `ntriples`, `N-Triples` - [N-Triples](https://www.w3.org/TR/n-triples/)
  * contains `quad`, e.g. `quad`, `nquads`, `N-Quads` - [N-Quads](https://www.w3.org/TR/n-quads/)
  * contains `n3`, e.g. `n3` - [N3](https://www.w3.org/TeamSubmission/n3/)
* `blankNodePrefix` (defaults to `b0_`) prefix forced on blank node names, e.g. `TriGWriter(["blankNodePrefix" => 'foo'])` will parse `_:bar` as `_:foobar`.
* `documentIRI` sets the base URI used to resolve relative URIs (not applicable if `format` indicates n-triples or n-quads)
* `lexer` allows usage of own lexer class. A lexer must provide following public methods:
  * `tokenize(string $input, bool $finalize = true): array<array{'subject': string, 'predicate': string, 'object': string, 'graph': string}>`
  * `tokenizeChunk(string $input): array<array{'subject': string, 'predicate': string, 'object': string, 'graph': string}>`
  * `end(): array<array{'subject': string, 'predicate': string, 'object': string, 'graph': string}>`
* `explicitQuantifiers` - [...]

#### Empty document base IRI

Some Turtle and N3 documents may use relative-to-the-base-IRI IRI syntax (see [here](https://www.w3.org/TR/turtle/#sec-iri) and [here](https://www.w3.org/TR/turtle/#sec-iri-references)), e.g.

```
<> <someProperty> "some value" .
```

To properly parse such documents the document base IRI must be known.
Otherwise we might end up with empty IRIs (e.g. for the subject in the example above).

Sometimes the base IRI is encoded in the document, e.g.

```
@base <http://some.base/iri/> .
<> <someProperty> "some value" .
```

but sometimes it is missing.
In such a case the [Turtle specification](https://www.w3.org/TR/turtle/#in-html-parsing) requires us to follow section 5.1.1 of the [RFC3986](http://www.ietf.org/rfc/rfc3986.txt) which says that if the base IRI is not encapsulated in the document, it should be assumed to be the document retrieval URI (e.g. the URL you downloaded the document from or a file path converted to an URL). Unfortunatelly this can not be guessed by the hardf parser and has to be provided by you using the `documentIRI` parser creation option, e.g.

```php
parser = new TriGParser(["documentIRI" => "http://some.base/iri/"]);
```

Long story short if you run into the `subject/predicate/object on line X can not be parsed without knowing the the document base IRI.(...)` error, please initialize the parser with the `documentIRI` option.

### Utility
```php
use pietercolpaert\hardf\Util;
```

A static class with a couple of helpful functions for handling our specific triple representation. It will help you to create and evaluate literals, IRIs, and expand prefixes.

```php
$bool = isIRI($term);
$bool = isLiteral($term);
$bool = isBlank($term);
$bool = isDefaultGraph($term);
$bool = inDefaultGraph($triple);
$value = getLiteralValue($literal);
$literalType = getLiteralType($literal);
$lang = getLiteralLanguage($literal);
$direction = getLiteralDirection($literal);
$bool = isPrefixedName($term);
$expanded = expandPrefixedName($prefixedName, $prefixes);
$iri = createIRI($iri);
$literalObject = createLiteral($value, $modifier = null);
```

See the documentation at https://github.com/RubenVerborgh/N3.js#utility for more information.

## Two executables

We also offer 2 simple tools in `bin/` as an example implementation: one validator and one translator. Try for example:
```bash
curl -H "accept: application/trig" http://fragments.dbpedia.org/2015/en | php bin/validator.php trig
curl -H "accept: application/trig" http://fragments.dbpedia.org/2015/en | php bin/convert.php trig n-triples
```

## RDF Working Group Test Suites

The official RDF Working Group test manifests live in [`w3c/rdf-tests`](https://github.com/w3c/rdf-tests). RDF 1.2 manifests are available under [`rdf/rdf12`](https://github.com/w3c/rdf-tests/tree/main/rdf/rdf12), including Turtle, TriG, N-Triples, and N-Quads suites.

This repository includes an optional [`rdf-test-suite.js`](https://github.com/rubensworks/rdf-test-suite.js) bridge in `spec/`. The JavaScript runner loads the W3C manifests, and `spec/hardf-rdf-test-engine.js` delegates parsing to `spec/hardf-rdf-test-parser.php`.

Install the optional Node dependencies:

```bash
npm install
```

Run individual RDF 1.2 leaf manifests:

```bash
npm run rdf12:ntriples:syntax
npm run rdf12:nquads:syntax
npm run rdf12:turtle:syntax
npm run rdf12:turtle:eval
npm run rdf12:trig:syntax
npm run rdf12:trig:eval
```

Or run grouped compliance reports that also include the relevant RDF 1.1 parser manifests:

```bash
npm run rdf12:ntriples
npm run rdf12:nquads
npm run rdf12:turtle
npm run rdf12:trig
npm run rdf12
```

The RDF 1.2 N-Triples and N-Quads syntax leaf manifests pass with this bridge. The grouped N-Triples and N-Quads commands still report some RDF 1.1 legacy parser failures. The RDF 1.2 root manifests also include C14N tests, but `rdf-test-suite.js` does not currently provide handlers for the RDF 1.2 C14N test types.

RDF/XML and RDF semantics manifests are not wired because hardf does not implement RDF/XML parsing or entailment. The Turtle and TriG RDF 1.2 syntax/eval manifests are useful as compliance reports, but currently still expose remaining hardf gaps around some annotation, blank-node, and `VERSION` negative cases.

## Performance

We compared the performance on two turtle files, and parsed it with the EasyRDF library in PHP, the N3.js library for NodeJS and with Hardf. These were the results:

| #triples | framework               | time (ms) | memory (MB) |
|----------:|-------------------------|------:|--------:|
|1,866    | __Hardf__ without opcache |  27.6   |   0.722     |
|1,866    | __Hardf__ with opcache    |   24.5   |    0.380    |
|1,866    | [EasyRDF](https://github.com/njh/easyrdf) without opcache |   5,166.5   |    2.772   |
|1,866    | [EasyRDF](https://github.com/njh/easyrdf) with opcache    |  5,176.2    |  2.421     |
|1,866    | [ARC2](https://github.com/semsol/arc2) with opcache | 71.9 | 1.966 |
| 1,866  |   [N3.js](https://github.com/RubenVerborgh/N3.js) |  24.0    |  28.xxx  |
| 3,896,560  |   __Hardf__ without opcache |  40,017.7    |  0.722   |
| 3,896,560  |   __Hardf__ with opcache |    33,155.3  |    0.380   |
| 3,896,560  |   [N3.js](https://github.com/RubenVerborgh/N3.js) |  7,004.0    |  59.xxx    |
| 3,896,560  |  [ARC2](https://github.com/semsol/arc2) with opcache | 203,152.6 | 3,570.808  |

## License, status and contributions
The hardf library is copyrighted by Ghent University - IMEC and contributors, and released under the [MIT License](https://github.com/pietercolpaert/hardf/blob/master/LICENSE).

Contributions are welcome, and bug reports or pull requests are always helpful.
If you plan to implement a larger feature, it's best to discuss this first by filing an issue.
