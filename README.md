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

```
'"Tom"@en-gb' // lowercase language
'"1"^^http://www.w3.org/2001/XMLSchema#integer' // no angular brackets <>
```

## Library functions

Install this library using [composer](http://getcomposer.org):

```bash
composer install pietercolpaert/hardf
```

Currently, we only have the `pietercolpaert\hardf\Util` class available, that will help you to create and evaluate literals, IRIs, and expand prefixes.

See the documentation at https://github.com/RubenVerborgh/N3.js#utility. Instead of N3Util, you will have to use `pietercolpaert\hardf::Util`.

