# hardf
[![Build Status](https://travis-ci.org/pietercolpaert/hardf.svg?branch=master)](https://travis-ci.org/pietercolpaert/hardf)

Current Status: early port of [N3.js](https://github.com/RubenVerborgh/N3.js) to PHP

Basic PHP library for RDF1.1. Currently provides simple tools (an Util library) for an array of triples/quads.

For now, [EasyRDF](https://github.com/njh/easyrdf) is the best PHP library for RDF (naming of this library is a contraction of "Hard" and "RDF", in which we try to make the point that you should at this point only use hardf when you know what you’re doing).
The EasyRDF library is a high-level library which abstracts all the difficult parts of dealing with RDF.
Hardf on the other hand, aims at a high performance for triple representations.
We will only support formats such as turtle or trig and n-triples or n-quads.
If you want other other formats, you will have to write some logic to load the triples into memory according to our triple representation (e.g., for JSON-LD, check out [ml/json-ld](https://github.com/lanthaler/JsonLD)).

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

### Util class
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

See the documentation at https://github.com/RubenVerborgh/N3.js#utility for more information about what the functions exactly do.

### TriGWriter class
```php
use pietercolpaert\hardf\TriGWriter
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
        ]
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
