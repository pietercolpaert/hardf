<?php

declare(strict_types=1);

include_once __DIR__.'/../vendor/autoload.php';

use pietercolpaert\hardf\DataModel\DataFactory;
use pietercolpaert\hardf\TriGWriter;

//Add prefixes in the constructor
$writer = new TriGWriter([
    'prefixes' => [
        'schema' => 'http://schema.org/',
        'dct' => 'http://purl.org/dc/terms/',
        'geo' => 'http://www.w3.org/2003/01/geo/wgs84_pos#',
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
    ],
]);

$writer->addPrefix('ex', 'http://example.org/');
$writer->addQuad(DataFactory::quad(
    DataFactory::namedNode('http://schema.org/Person'),
    DataFactory::namedNode('http://purl.org/dc/terms/title'),
    DataFactory::literal('Person', 'en'),
    DataFactory::namedNode('http://example.org/#test')
));
$writer->addQuad(DataFactory::quad(
    DataFactory::namedNode('http://schema.org/Person'),
    DataFactory::namedNode('http://schema.org/label'),
    DataFactory::literal('Person', 'en'),
    DataFactory::namedNode('http://example.org/#test')
));
$writer->addQuad(DataFactory::quad(
    DataFactory::namedNode('http://example.org/1'),
    DataFactory::namedNode('http://purl.org/dc/terms/title'),
    DataFactory::literal('Person1', 'en'),
    DataFactory::namedNode('http://example.org/#test')
));
$writer->addQuad(DataFactory::quad(
    DataFactory::namedNode('http://example.org/1'),
    DataFactory::namedNode('http://www.w3.org/1999/02/22-rdf-syntax-ns#type'),
    DataFactory::namedNode('http://schema.org/Person'),
    DataFactory::namedNode('http://example.org/#test')
));
$writer->addQuad(DataFactory::quad(
    DataFactory::namedNode('http://example.org/2'),
    DataFactory::namedNode('http://purl.org/dc/terms/title'),
    DataFactory::directionalLiteral('Person2', 'en', 'ltr'),
    DataFactory::namedNode('http://example.org/#test')
));
$writer->addQuad(DataFactory::quad(
    DataFactory::namedNode('http://schema.org/Person'),
    DataFactory::namedNode('http://purl.org/dc/terms/title'),
    DataFactory::literal('Person', 'en'),
    DataFactory::namedNode('http://example.org/#test2')
));
$writer->addQuad(DataFactory::quad(
    DataFactory::namedNode('http://example.org/claim'),
    DataFactory::namedNode('http://example.org/about'),
    DataFactory::tripleTerm(
        DataFactory::namedNode('http://example.org/1'),
        DataFactory::namedNode('http://purl.org/dc/terms/title'),
        DataFactory::literal('Person1', 'en')
    ),
    DataFactory::namedNode('http://example.org/#test')
));
echo $writer->end();
