<?php

declare(strict_types=1);

include_once __DIR__.'/../vendor/autoload.php';

use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\Util;

const XSD_STRING = 'http://www.w3.org/2001/XMLSchema#string';

/**
 * @return array<string, mixed>
 */
function termToJson($term): array
{
    if (\is_array($term) && isset($term['type']) && 'TripleTerm' === $term['type']) {
        return [
            'termType' => 'Quad',
            'subject' => termToJson($term['subject']),
            'predicate' => termToJson($term['predicate']),
            'object' => termToJson($term['object']),
            'graph' => isset($term['graph']) ? termToJson($term['graph']) : ['termType' => 'DefaultGraph', 'value' => ''],
        ];
    }

    if (!\is_string($term) || '' === $term) {
        return ['termType' => 'DefaultGraph', 'value' => ''];
    }

    if (Util::isBlank($term)) {
        return ['termType' => 'BlankNode', 'value' => substr($term, 2)];
    }

    if (Util::isLiteral($term)) {
        $type = Util::getLiteralType($term);
        $language = Util::getLiteralLanguage($term);
        $direction = Util::getLiteralDirection($term);
        $json = [
            'termType' => 'Literal',
            'value' => Util::getLiteralValue($term),
            'datatype' => $type ?: XSD_STRING,
            'language' => $language,
        ];
        if ('' !== $direction) {
            $json['direction'] = $direction;
        }

        return $json;
    }

    return ['termType' => 'NamedNode', 'value' => $term];
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
    $triples = $parser->parse(isset($request['data']) ? $request['data'] : '');
    $json = [];
    foreach ($triples as $triple) {
        $json[] = [
            'subject' => termToJson($triple['subject']),
            'predicate' => termToJson($triple['predicate']),
            'object' => termToJson($triple['object']),
            'graph' => termToJson(isset($triple['graph']) ? $triple['graph'] : ''),
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
