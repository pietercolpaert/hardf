<?php

declare(strict_types=1);

include_once __DIR__.'/../vendor/autoload.php';
include_once __DIR__.'/perf-util.php';

$filename = perfGetFilename($argv);
$test = microtime(true);

try {
    $parser = ARC2::getRDFParser();
    $parser->parse('file://'.$filename, file_get_contents($filename));
    $errors = $parser->getErrors();
    if (!empty($errors)) {
        throw new Exception(implode('; ', $errors));
    }

    perfPrintSuccess($filename, $test, count($parser->getTriples()));
} catch (Throwable $error) {
    perfPrintFailure($filename, $test, $error);
    exit(1);
}