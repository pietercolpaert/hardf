# hardf
[![Build Status](https://travis-ci.org/pietercolpaert/hardf.svg?branch=master)](https://travis-ci.org/pietercolpaert/hardf)

**hardf** is a PHP library that lets you handle RDF easily. It offers:
 - [**Parsing**](#parsing) triples/quads from [Turtle](http://www.w3.org/TR/turtle/), [TriG](http://www.w3.org/TR/trig/), [N-Triples](http://www.w3.org/TR/n-triples/), [N-Quads](http://www.w3.org/TR/n-quads/), and [Notation3 (N3)](https://www.w3.org/TeamSubmission/n3/)
 - [**Writing**](#writing) triples/quads to [Turtle](http://www.w3.org/TR/turtle/), [TriG](http://www.w3.org/TR/trig/), [N-Triples](http://www.w3.org/TR/n-triples/), and [N-Quads](http://www.w3.org/TR/n-quads/)

Both the parser as the serializer have _streaming_ support.

_This library is a port of [N3.js](https://github.com/RubenVerborgh/N3.js) to PHP_

## Triple Representation

We use the triple representation in  PHP ported from NodeJS N3.js library. Check https://github.com/RubenVerborgh/N3.js#triple-representation

On purpose, we focused on performance, and not on developer friendliness.
We have thus implemented this triple representation using associative arrays rather than PHP object. Thus, the same that holds for N3.js, is now an array. E.g.:

```php
<?php
$triple = [
    'subject' =>   'http://example.org/cartoons#Tom',
    'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
    'object' =>    'http://example.org/cartoons#Cat'
    ,'graph' =>     'http://example.org/mycartoon' #optional
    ];
```

Encode literals as follows (similar to N3.js)

```php
'"Tom"@en-gb' // lowercase language
'"1"^^http://www.w3.org/2001/XMLSchema#integer' // no angular brackets <>
```

## Library functions

Install this library using [composer](http://getcomposer.org):

```bash
composer install pietercolpaert/hardf
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
echo $writer->end();
```

All methods (some may throw an exception):
```php
//The method names should speak for themselves:
$writer = new TriGWriter(["prefixes": [ /* ... */]]);
$writer->addTriple($subject, $predicate, $object, $graphl);
$writer->addTriples($triples);
$writer->addPrefix($prefix, $iri);
$writer->addPrefixes($prefixes);
//Creates blank node($predicate and/or $object are optional)
$writer->blank($predicate, $object);
//Creates rdf:list with $elements
$list = $writer->list($elements);

//Returns the current output it is already able to create and clear the internal memory use (useful for streaming)
$out .= $writer->read();

//Call this at the end. The return value will be the full triple output, or the rest of the output such as closing dots and brackets
$out .= $writer->end();
```

### Parsing

Next to [TriG](https://www.w3.org/TR/trig/), the TriGParser class also parses [Turtle](https://www.w3.org/TR/turtle/), [N-Triples](https://www.w3.org/TR/n-triples/), [N-Quads](https://www.w3.org/TR/n-quads/) and the [W3C Team Submission N3](https://www.w3.org/TeamSubmission/n3/)

Basic example:

```php
use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\TriGWriter;

echo "--- First, simple implementation ---\n";
$parser = new TriGParser(["format" => "n-quads"]); //also parser n-triples, n3, turtle and trig. Format is optional
$writer = new TriGWriter();
$triples = $parser->parse("<A> <B> <C> <G> .");
$writer->addTriples($triples);
echo $writer->end();

echo "--- Second streaming implementation with callbacks ---\n";
$parser = new TriGParser();
$writer = new TriGWriter(["format"=>"trig"]);
$parser->parse("<http://A> <https://B> <http://C> <http://G> . <A2> <https://B2> <http://C2> <http://G3> .", function ($e, $triple) use ($writer) {
    if (!$e && $triple)
        $writer->addTriple($triple);
    else if (!$triple)
        echo $writer->end();
    else
        echo "Error occured: " . $e;
});
```

Parse chunks until end also works this way:
```php
$parser->parseChunk(chunk, callback);
$parser->end(callback);
```

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
