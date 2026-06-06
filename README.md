# The Hardf RDF1.2 Turtle, N-Triples, N-Quads, and TriG parser for PHP

[![PHP CI](https://github.com/pietercolpaert/hardf/actions/workflows/php-ci.yml/badge.svg)](https://github.com/pietercolpaert/hardf/actions/workflows/php-ci.yml)
[![W3C RDF1.2 spec compliance](https://github.com/pietercolpaert/hardf/actions/workflows/spec-compliance.yml/badge.svg)](https://github.com/pietercolpaert/hardf/actions/workflows/spec-compliance.yml)
[![Latest stable release](https://img.shields.io/packagist/v/pietercolpaert/hardf)](https://packagist.org/packages/pietercolpaert/hardf)

**Hardf** is a PHP 7.1+ library that lets you handle Linked Data (RDF1.2). It offers [**parsing**](#parsing) from and [**writing**](#writing) in [Turtle](http://www.w3.org/TR/turtle/), [TriG](http://www.w3.org/TR/trig/), [N-Triples](http://www.w3.org/TR/n-triples/), and [N-Quads](http://www.w3.org/TR/n-quads/). Both the parser and the serializer have _streaming_ support.

Hardf also supports [RDF1.2](https://www.w3.org/TR/rdf12-concepts/) features that are relevant to this representation, including triple terms, reified triples, annotation syntax, directional language literals, `VERSION` declarations, and [RDF Messages](https://w3c-cg.github.io/rsp/spec/messages). Conformances is [tested using the official test suites](#rdf-working-group-test-suites).

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

RDF1.2 triple terms are represented as structured arrays, not as serialized strings:

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

RDF1.2 triple terms are emitted as arrays with `type => TripleTerm`. Reified triple syntax emits an `rdf:reifies` triple whose object is such a triple term.

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

Parsing RDF1.2 triple terms:

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
* `relax` enables the relaxed N-Triples/N-Quads hot path for trusted benchmark input. Keep this disabled for conformance tests.
* `namedNodeCacheSize` sets the bounded cache size for recurring named nodes in the relaxed N-Triples/N-Quads hot path, such as predicates, datatypes, and graph IRIs. Defaults to `2048`.
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

The official RDF Working Group test manifests live in [`w3c/rdf-tests`](https://github.com/w3c/rdf-tests). RDF1.2 manifests are available under [`rdf/rdf12`](https://github.com/w3c/rdf-tests/tree/main/rdf/rdf12), including Turtle, TriG, N-Triples, and N-Quads suites.

This repository includes an optional [`rdf-test-suite.js`](https://github.com/rubensworks/rdf-test-suite.js) bridge in `spec/`. The JavaScript runner loads the W3C manifests, and `spec/hardf-rdf-test-engine.js` delegates parsing to `spec/hardf-rdf-test-parser.php`.

Install the optional Node dependencies:

```bash
npm install
```

Run individual RDF1.2 leaf manifests:

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

Generate machine-readable EARL output by using the scripts with the `:earl` suffix. The metadata is defined in `spec/earl-metadata.json`.

```bash
npm run rdf12:turtle:eval:earl
npm run rdf11:trig:earl
```

All RDF1.2 compliance commands pass. The RDF1.2 root manifests also include C14N tests, but `rdf-test-suite.js` does not currently provide handlers for the RDF1.2 C14N test types.

RDF/XML and RDF semantics manifests are not wired because hardf does not implement RDF/XML parsing or entailment. The compliance scripts in this repository therefore focus on the RDF parser manifests for Turtle, TriG, N-Triples, and N-Quads.

## Performance

In the PHP ecosystem, Hardf is the only parser in this benchmark that supports the full feature set tested here: RDF 1.1 plus RDF1.2 features such as named graphs and triple terms. EasyRDF and ARC2 are useful comparison points for plain RDF1.0-compatible N-Triples, but they are not like-for-like RDF1.2 comparisons.

Strict parsing keeps the full conformance parser path. A strict scanner prototype was measured, but its validation overhead was slower than the existing parser path on PHP 8.3. For trusted machine-generated N-Triples and N-Quads input, the `relax` option enables a single-pass scanner hot path. The relaxed scanner keeps input as strings, scans by byte offset, emits quads immediately for streaming callbacks, buffers only incomplete trailing lines, and falls back to the general parser when it sees escapes, unsupported syntax, comments, or unusual whitespace. Spec-test paths run in strict mode.

The benchmark generates datasets at $10^4$, $10^5$, and $10^6$ statements and reports elapsed time plus statements/second. The RDF1.2 dataset includes default-graph triples, named-graph quads, IRI objects, string literals, language-tagged literals, integer/decimal/boolean literals, and triple terms. EasyRDF and ARC2 comparisons are limited to a plain RDF1.0-compatible N-Triples dataset.

```bash
# Quick benchmark, useful before committing
npm run bench:rdf:quick

# Full benchmark
npm run bench:rdf
```

CLI benchmarks run with `opcache.enable_cli=1` and JIT disabled (`opcache.jit_buffer_size=0`) for reproducibility.

The measurements below were taken on 2026-06-06 with PHP 8.3.6 CLI by running `npm run bench:rdf`.

| dataset | parser | statements | elapsed ms | statements/sec |
|---------|--------|-----------:|-----------:|---------------:|
| RDF1.2 mixed N-Quads | Hardf strict | 10,000 | 68.21 | 146,602 |
| RDF1.2 mixed N-Quads | Hardf relax | 10,000 | 25.72 | 388,876 |
| Plain RDF1.0 N-Triples | Hardf strict | 10,000 | 48.97 | 204,216 |
| Plain RDF1.0 N-Triples | EasyRDF | 10,000 | 30.48 | 328,126 |
| Plain RDF1.0 N-Triples | ARC2 | 10,000 | 125.48 | 79,691 |
| RDF1.2 mixed N-Quads | Hardf strict | 100,000 | 664.83 | 150,415 |
| RDF1.2 mixed N-Quads | Hardf relax | 100,000 | 223.27 | 447,882 |
| Plain RDF1.0 N-Triples | Hardf strict | 100,000 | 461.03 | 216,905 |
| Plain RDF1.0 N-Triples | EasyRDF | 100,000 | 354.94 | 281,738 |
| Plain RDF1.0 N-Triples | ARC2 | 100,000 | 1,287.55 | 77,667 |
| RDF1.2 mixed N-Quads | Hardf strict | 1,000,000 | 6,647.96 | 150,422 |
| RDF1.2 mixed N-Quads | Hardf relax | 1,000,000 | 2,226.82 | 449,072 |
| Plain RDF1.0 N-Triples | Hardf strict | 1,000,000 | 4,770.10 | 209,639 |
| Plain RDF1.0 N-Triples | EasyRDF | 1,000,000 | 4,639.85 | 215,524 |
| Plain RDF1.0 N-Triples | ARC2 | 1,000,000 | 13,689.83 | 73,047 |

The main result is that strict RDF1.2 parsing remains stable and linear while preserving conformance. On the mixed RDF1.2 N-Quads dataset, Hardf strict reaches about $1.5 \times 10^5$ statements/second at $10^6$ statements. The relaxed scanner reaches about $4.5 \times 10^5$ statements/second on the same generated dataset, roughly `3.0x` faster than strict mode.

The plain RDF1.0 N-Triples benchmark isolates simple line-based triples. In that dataset, Hardf strict reaches about $2.1 \times 10^5$ statements/second at $10^6$ statements, close to EasyRDF on the same RDF1.0-only input and about `2.9x` faster than ARC2. This comparison should not be extrapolated to RDF1.2 support, because the RDF1.2 benchmark includes named graphs and triple terms that EasyRDF and ARC2 do not cover here.

Across all measured sizes, throughput is roughly linear. Strict mode favors correctness and broad syntax support; relaxed mode is the high-throughput path for trusted generated line-format data.

## License, status and contributions

The Hardf library is copyrighted by Ghent University - IMEC and contributors, and released under the [MIT License](https://github.com/pietercolpaert/hardf/blob/master/LICENSE).

Contributions are welcome, and bug reports or pull requests are always helpful.
If you plan to implement a larger feature, it's best to discuss this first by filing an issue.
