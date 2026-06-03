# The Hardf RDF 1.2 Turtle, N-Triples, N-Quads, and TriG parser for PHP

**Hardf** is a PHP 7.1+ library that lets you handle Linked Data (RDF 1.2). It offers [**parsing**](#parsing) and [**Writing**](#writing) triples/quads form or in [Turtle](http://www.w3.org/TR/turtle/), [TriG](http://www.w3.org/TR/trig/), [N-Triples](http://www.w3.org/TR/n-triples/), and [N-Quads](http://www.w3.org/TR/n-quads/). Both the parser and the serializer have _streaming_ support.

Hardf also supports [RDF 1.2](https://www.w3.org/TR/rdf12-concepts/) features that are relevant to this representation, including triple terms, reified triples, annotation syntax, directional language literals, `VERSION` declarations, and [RDF Messages](https://w3c-cg.github.io/rsp/spec/messages). Conformances is [tested using the official test suites](#rdf-working-group-test-suites).

This library was started as a port of [N3.js](https://github.com/rdfjs/N3.js/tree/v0.10.0) to PHP.

## Triple Representation

On purpose, we focused on performance, and not on developer friendliness.
We have thus implemented this triple representation using associative arrays rather than PHP objects. For example:

```php
<?php
$triple = [
    'subject' =>   'http://example.org/cartoons#Tom',
    'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
    'object' =>    'http://example.org/cartoons#Cat',
    'graph' =>     'http://example.org/mycartoon', // optional
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

A class that can be instantiated to write TriG or Turtle.

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
    "format" => "n-quads" // Other possible values: n-quads, trig, or turtle
]);

$writer->addPrefix("ex", "http://example.org/");
$writer->addTriple("schema:Person", "dct:title", "\"Person\"@en", "http://example.org/#test");
$writer->addTriple("schema:Person", "schema:label", "\"Person\"@en", "http://example.org/#test");
$writer->addTriple("ex:1", "dct:title", "\"Person1\"@en", "http://example.org/#test");
$writer->addTriple("ex:1", "http://www.w3.org/1999/02/22-rdf-syntax-ns#type", "schema:Person", "http://example.org/#test");
$writer->addTriple("ex:2", "dct:title", "\"Person2\"@en", "http://example.org/#test");
$writer->addTriple("schema:Person", "dct:title", "\"Person\"@en", "http://example.org/#test2");
$writer->addTriple("ex:claim", "ex:source", [
    "type" => "TripleTerm",
    "subject" => "ex:1",
    "predicate" => "dct:title",
    "object" => "\"Person1\"@en"
], "http://example.org/#test");
echo $writer->end();
```

#### All methods
```php
// The method names should speak for themselves:
$writer = new TriGWriter(["prefixes" => [ /* ... */]]);
$writer->addTriple($subject, $predicate, $object, $graph);
$writer->addTriples($triples);
$writer->addMessage($quads); // requires ["messages" => true]
$writer->addPrefix($prefix, $iri);
$writer->addPrefixes($prefixes);
// Creates a blank node ($predicate and/or $object are optional)
$writer->blank($predicate, $object);
// Creates an rdf:list with $elements
$list = $writer->addList($elements);

// Returns the output generated so far and clears the internal buffer (useful for streaming)
$out .= $writer->read();
// Alternatively, you can listen for new chunks through a callback:
$writer->setReadCallback(function ($output) { echo $output });

// Call this at the end. The return value will be the full output, or the remaining output such as closing dots and brackets, unless a callback was set.
$out .= $writer->end();
// OR
$writer->end();
```

#### RDF Messages

To write an RDF Message Log, create the writer with `messages => true` and call `addMessage()` for each message. If you also pass a `version`, Hardf will emit a `VERSION` declaration with the `-messages` suffix automatically.

```php
$writer = new TriGWriter([
    "format" => "n-triples",
    "messages" => true,
    "version" => "1.2",
]);

$writer->addMessage([
    [
        "subject" => "http://example.org/message-1",
        "predicate" => "http://example.org/text",
        "object" => '"Hello"',
        "graph" => "",
    ],
]);

$writer->addMessage([]); // empty message

echo $writer->end();
```

This writes:

```turtle
VERSION "1.2-messages"
<http://example.org/message-1> <http://example.org/text> "Hello".
MESSAGE
MESSAGE
```

### Parsing

Next to [TriG](https://www.w3.org/TR/trig/), the TriGParser class also parses [Turtle](https://www.w3.org/TR/turtle/), [N-Triples](https://www.w3.org/TR/n-triples/), and [N-Quads](https://www.w3.org/TR/n-quads/).

RDF 1.2 triple terms are emitted as arrays with `type => TripleTerm`. Reified triple syntax emits an `rdf:reifies` triple whose object is such a triple term.

RDF Message Logs are enabled by a `VERSION` label with the `-messages` suffix, such as `VERSION "1.2-messages"`. When parsing in streaming mode, the triple callback receives the current message counter as its fourth argument. Blank node labels are scoped per message, so the same blank node labels may legally reappear in later messages.

If you construct the parser with `messages => true` and use `parse()` without a callback, Hardf returns the parsed data as an array of messages, where each message is an array of quads.

#### All methods

```php
$parser = new TriGParser($options, $tripleCallback, $prefixCallback);
$parser->setTripleCallback($function);
$parser->setPrefixCallback($function);
$parser->parse($input, $tripleCallback, $prefixCallback);
$parser->parseChunk($input);
$parser->end();
```

The triple callback signature is:

```php
function ($error, $triple = null, $prefixes = null, $messageCounter = null) {
    // ...
}
```

For normal RDF parsing, `$messageCounter` stays `null`. In RDF Message mode, it starts at `0` for the first message and increments at each `MESSAGE` or `@message` delimiter.

When `messages => true` is set and no callback is passed, the return type becomes effectively:

```php
array<int, array<int, array<string, mixed>>>
```

That is, an array of messages, each containing an array of quads.

#### Basic examples for small files

Using return values and passing these to a writer:
```php
use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\TriGWriter;
$parser = new TriGParser(["format" => "n-quads"]); // Also parses N-Triples, N3, Turtle, and TriG. The format is optional.
$writer = new TriGWriter();
$triples = $parser->parse("<A> <B> <C> <G> .");
$writer->addTriples($triples);
echo $writer->end();
```

Using callbacks and passing these to a writer:
```php
$parser = new TriGParser();
$writer = new TriGWriter(["format" => "trig"]);
$parser->parse("<http://A> <https://B> <http://C> <http://G> . <A2> <https://B2> <http://C2> <http://G3> .", function ($e, $triple) use ($writer) {
    if (isset($e)) {
        echo "Error occurred: ".$e->getMessage();
    } elseif (isset($triple)) {
        $writer->addTriple($triple);
        echo $writer->read(); //write out what we have so far
    } else { // signals the end of the file
        echo $writer->end();
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

When you need to parse a large file, you will want to parse chunks and process them incrementally. You can do that as follows:

```php
$writer = new TriGWriter(["format" => "n-quads"]);
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
$parser->end(); // Needs to be called
```

#### Parsing RDF Messages

```php
$parser = new TriGParser(["format" => "n-triples"]);
$parser->parse("VERSION \"1.2-messages\"\n<a> <b> <c> .\nMESSAGE\n<d> <e> <f> .\n", function ($error, $triple = null, $prefixes = null, $messageCounter = null) {
    if ($error) {
        throw $error;
    }

    if ($triple) {
        echo "message #".$messageCounter."\n";
        var_dump($triple);
    }
});
```

The callback is invoked in two distinct situations:

1. **Triple event** — `$triple` is set, `$prefixes` is `null`. `$messageCounter` is the index (starting at `0`) of the message this triple belongs to.
2. **End-of-stream event** — `$triple` is `null` and `$prefixes` is an array (possibly empty). `$messageCounter` is the index of the **last active** message (the one that was still open when the stream ended).

If you want the whole RDF Message Log at once instead of streaming callbacks:

```php
$parser = new TriGParser([
    "format" => "n-triples",
    "messages" => true,
]);

$messages = $parser->parse(
    "VERSION \"1.2-messages\"\n".
    "<http://example.org/a> <http://example.org/b> <http://example.org/c> .\n".
    "MESSAGE\n".
    "<http://example.org/d> <http://example.org/e> <http://example.org/f> .\n"
);

// $messages is:
// [
//   [
//     ['subject' => 'http://example.org/a', 'predicate' => 'http://example.org/b', 'object' => 'http://example.org/c', 'graph' => ''],
//   ],
//   [
//     ['subject' => 'http://example.org/d', 'predicate' => 'http://example.org/e', 'object' => 'http://example.org/f', 'graph' => ''],
//   ],
// ]
```

#### Parser options

* `format` input format (case-insensitive)
  * if not provided or not matching any options below, then any [Turtle](https://www.w3.org/TR/turtle/), [TriG](https://www.w3.org/TR/trig/), [N-Triples](https://www.w3.org/TR/n-triples/) or [N-Quads](https://www.w3.org/TR/n-quads/) input can be parsed (but NOT the [N3](https://www.w3.org/TeamSubmission/n3/))
  * `turtle` - [Turtle](https://www.w3.org/TR/turtle/)
  * `trig` - [TriG](https://www.w3.org/TR/trig/)
  * contains `triple`, e.g. `triple`, `ntriples`, `N-Triples` - [N-Triples](https://www.w3.org/TR/n-triples/)
  * contains `quad`, e.g. `quad`, `nquads`, `N-Quads` - [N-Quads](https://www.w3.org/TR/n-quads/)
  * contains `n3`, e.g. `n3` - [N3](https://www.w3.org/TeamSubmission/n3/)
* `blankNodePrefix` (defaults to `b0_`) prefix forced on blank node names, e.g. `TriGParser(["blankNodePrefix" => 'foo'])` will parse `_:bar` as `_:foobar`.
* `documentIRI` sets the base URI used to resolve relative URIs (not applicable if `format` indicates n-triples or n-quads)
* `lexer` allows usage of your own lexer class. A lexer must provide the following public methods:
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
In such a case, the [Turtle specification](https://www.w3.org/TR/turtle/#in-html-parsing) requires us to follow section 5.1.1 of [RFC 3986](http://www.ietf.org/rfc/rfc3986.txt), which says that if the base IRI is not specified in the document, it should be assumed to be the document retrieval URI (e.g. the URL you downloaded the document from or a file path converted to a URL). Unfortunately, this cannot be guessed by the Hardf parser and has to be provided using the `documentIRI` parser option, e.g.

```php
$parser = new TriGParser(["documentIRI" => "http://some.base/iri/"]);
```

Long story short: if you run into the `subject/predicate/object on line X can not be parsed without knowing the the document base IRI.(...)` error, please initialize the parser with the `documentIRI` option.

### Utility
```php
use pietercolpaert\hardf\Util;
```

A static class with a couple of helpful functions for handling our specific triple representation. It helps you create and evaluate literals and IRIs, and expand prefixes.

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

All RDF 1.2 compliance commands pass. The RDF 1.2 root manifests also include C14N tests, but `rdf-test-suite.js` does not currently provide handlers for the RDF 1.2 C14N test types.

RDF/XML and RDF semantics manifests are not wired because hardf does not implement RDF/XML parsing or entailment. The compliance scripts in this repository therefore focus on the RDF parser manifests for Turtle, TriG, N-Triples, and N-Quads.

## Performance

In the PHP ecosystem, Hardf is the only library here that supports RDF 1.1 and RDF 1.2 features such as named graphs and triple terms. EasyRDF and ARC2 are still useful comparison points, but they do not cover that full feature set.

Because of that, the benchmark below uses a generated **N-Triples** dataset, not TriG. The input is generated through Hardf's own `TriGWriter` in `N-Triples` mode and contains only plain triples, with a mix of IRI objects, literal objects, and occasional blank nodes. This gives all compared parsers the same RDF 1.0-compatible input while still reflecting realistic parser work.

The measurements below were taken with PHP 8.3.6 and Node.js 25.9.0 using `php perf/compare-hardf-n3js.php`. EasyRDF and ARC2 were measured with CLI opcache enabled. Hardf was measured both with and without CLI opcache enabled. N3.js is included as a reference implementation on a very fast runtime, so it is expected to win on absolute throughput.

That expectation does show up in the results, but the interesting part is the size of the gap: Hardf remains within a single-digit factor of N3.js across the whole range and scales roughly linearly from $10^5$ to $10^7$ triples. CLI opcache helps Hardf modestly on runtime and significantly on reported PHP memory. EasyRDF and ARC2 fall further behind as the data grows.

We report on the findings for increasing number of triples.

Note that the memory figures are runtime-specific and therefore not perfectly comparable across languages: PHP reports `memory_get_usage()`, while Node.js reports V8 `heapUsed`. The timing results are the more meaningful cross-runtime comparison.


For __100,000 triples__:

| framework | time (ms) | memory (MB) | slower than N3.js |
|-----------|----------:|------------:|------------------:|
| __Hardf__ without opcache | 382 | 1.849 | 3.41x |
| __Hardf__ with opcache | 372 | 0.540 | 3.32x |
| [EasyRDF](https://github.com/easyrdf/easyrdf) with opcache | 303 | 181.346 | 2.71x |
| [ARC2](https://github.com/semsol/arc2) with opcache | 1,133 | 83.435 | 10.12x |
| [N3.js](https://github.com/rdfjs/N3.js) | 112 | 6.328 | 1.00x |

For __1,000,000 triples__:

| framework | time (ms) | memory (MB) | slower than N3.js |
|-----------|----------:|------------:|------------------:|
| __Hardf__ without opcache | 3,961 | 1.849 | 4.62x |
| __Hardf__ with opcache | 3,880 | 0.540 | 4.52x |
| [EasyRDF](https://github.com/easyrdf/easyrdf) with opcache | 4,247 | 1,788.624 | 4.95x |
| [ARC2](https://github.com/semsol/arc2) with opcache | 12,463 | 826.056 | 14.53x |
| [N3.js](https://github.com/rdfjs/N3.js) | 858 | 9.263 | 1.00x |

For __10,000,000 triples__:

| framework | time (ms) | memory (MB) | slower than N3.js |
|-----------|----------:|------------:|------------------:|
| __Hardf__ without opcache | 41,607 | 1.849 | 5.19x |
| __Hardf__ with opcache | 39,098 | 0.540 | 4.88x |
| [EasyRDF](https://github.com/easyrdf/easyrdf) with opcache | 125,834 | 18,041.405 | 15.70x |
| [ARC2](https://github.com/semsol/arc2) with opcache | 147,273 | 8,352.265 | 18.37x |
| [N3.js](https://github.com/rdfjs/N3.js) | 8,017 | 30.336 | 1.00x |


1. N3.js on Node.js 25 is faster, which is expected, but Hardf stays surprisingly close for a native PHP parser: about `3.32x` to `4.88x` slower with opcache enabled, and about `3.41x` to `5.19x` slower without it.
2. Hardf remains effective over the tested range and is substantially faster than ARC2 at every size, while also overtaking EasyRDF on the larger datasets.

## License, status and contributions

The Hardf library is copyrighted by Ghent University - IMEC and contributors, and released under the [MIT License](https://github.com/pietercolpaert/hardf/blob/master/LICENSE).

Contributions are welcome, and bug reports or pull requests are always helpful.
If you plan to implement a larger feature, it's best to discuss this first by filing an issue.
