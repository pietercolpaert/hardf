<?php

declare(strict_types=1);

if (3 !== count($argv)) {
    echo "Usage: generate-rdf12-nq.php output-file statement-count\n";
    exit(1);
}

$outputFile = $argv[1];
$statementCount = (int) $argv[2];

if ($statementCount <= 0) {
    echo "Statement count must be a positive integer\n";
    exit(1);
}

$directory = dirname($outputFile);
if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    echo 'Could not create directory: '.$directory."\n";
    exit(1);
}

$handle = fopen($outputFile, 'wb');
if (false === $handle) {
    echo 'Could not open output file: '.$outputFile."\n";
    exit(1);
}

for ($index = 0; $index < $statementCount; ++$index) {
    $subject = '<http://example.org/s'.$index.'>';
    $predicate = '<http://example.org/p'.($index % 17).'>';

    switch ($index % 8) {
        case 0:
            $object = '<http://example.org/o'.$index.'>';
            break;
        case 1:
            $object = '"literal '.$index.'"';
            break;
        case 2:
            $object = '"hello '.$index.'"@en';
            break;
        case 3:
            $object = '"'.($index % 1000).'"^^<http://www.w3.org/2001/XMLSchema#integer>';
            break;
        case 4:
            $object = '"'.($index % 1000).'.25"^^<http://www.w3.org/2001/XMLSchema#decimal>';
            break;
        case 5:
            $object = '"'.(0 === $index % 2 ? 'true' : 'false').'"^^<http://www.w3.org/2001/XMLSchema#boolean>';
            break;
        case 6:
            $object = '<<(<http://example.org/ts'.$index.'> <http://example.org/tp'.($index % 7).'> <http://example.org/to'.$index.'>)>>';
            break;
        default:
            $object = '<<(<http://example.org/ts'.$index.'> <http://example.org/tp'.($index % 7).'> "tv '.$index.'")>>';
            break;
    }

    if (0 === $index % 4) {
        $line = $subject.' '.$predicate.' '.$object.' .';
    } else {
        $line = $subject.' '.$predicate.' '.$object.' <http://example.org/g'.($index % 11).'> .';
    }

    if (false === fwrite($handle, $line."\n")) {
        fclose($handle);
        throw new RuntimeException('Could not write to output file: '.$outputFile);
    }
}

fclose($handle);
echo 'Generated '.$statementCount.' RDF1.2 statements at '.$outputFile."\n";
