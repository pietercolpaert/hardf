<?php

declare(strict_types=1);

include_once __DIR__.'/../vendor/autoload.php';

use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\DataModel\BlankNode;
use pietercolpaert\hardf\DataModel\DefaultGraph;
use pietercolpaert\hardf\DataModel\Literal;
use pietercolpaert\hardf\DataModel\NamedNode;
use pietercolpaert\hardf\DataModel\Term;
use pietercolpaert\hardf\DataModel\TripleTerm;

const XSD_STRING = 'http://www.w3.org/2001/XMLSchema#string';

/**
 * @return array<string, mixed>
 */
function termToJson(Term $term): array
{
    if ($term instanceof TripleTerm) {
        return [
            'termType' => 'TripleTerm',
            'subject' => termToJson($term->subject),
            'predicate' => termToJson($term->predicate),
            'object' => termToJson($term->object),
        ];
    }

    if ($term instanceof DefaultGraph) {
        return ['termType' => 'DefaultGraph', 'value' => ''];
    }

    if ($term instanceof BlankNode) {
        return ['termType' => 'BlankNode', 'value' => $term->value()];
    }

    if ($term instanceof Literal) {
        $json = [
            'termType' => 'Literal',
            'value' => $term->value(),
            'datatype' => $term->datatype->value(),
        ];
        if ('' !== $term->language) {
            $json['language'] = $term->language;
        }
        if ('' !== $term->direction) {
            $json['direction'] = $term->direction;
        }

        return $json;
    }

    if ($term instanceof NamedNode) {
        return ['termType' => 'NamedNode', 'value' => $term->value()];
    }

    throw new \InvalidArgumentException('Unsupported term type: '.$term::class);
}

$input = stream_get_contents(STDIN);
$request = json_decode($input, true);
if (!\is_array($request)) {
    if (false === @fwrite(STDERR, "Expected JSON request on stdin.\n")) {
        echo "Expected JSON request on stdin.\n";
    }
    exit(1);
}

try {
    $parser = new TriGParser([
        'documentIRI' => isset($request['baseIRI']) ? $request['baseIRI'] : null,
        'format' => isset($request['format']) ? $request['format'] : null,
    ]);
    $quads = iterator_to_array($parser->parse(isset($request['data']) ? $request['data'] : ''), false);
    $json = [];
    foreach ($quads as $quad) {
        $json[] = [
            'subject' => termToJson($quad->getSubject()),
            'predicate' => termToJson($quad->getPredicate()),
            'object' => termToJson($quad->getObject()),
            'graph' => termToJson($quad->getGraph()),
        ];
    }
    echo json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
} catch (\Throwable $e) {
    $message = $e->getMessage()."\n";
    if (false === @fwrite(STDERR, $message)) {
        echo $message;
    }
    exit(1);
}
