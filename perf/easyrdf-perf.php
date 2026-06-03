<?php

declare(strict_types=1);

include_once __DIR__.'/../vendor/autoload.php';
include_once __DIR__.'/perf-util.php';

use EasyRdf\Graph;

$filename = perfGetFilename($argv);
$test = microtime(true);

try {
    $graph = new Graph();
    $count = (int) $graph->parseFile($filename, 'ntriples', 'file://'.$filename);
    perfPrintSuccess($filename, $test, $count);
} catch (Throwable $error) {
    perfPrintFailure($filename, $test, $error);
    exit(1);
}
