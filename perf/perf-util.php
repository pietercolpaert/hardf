<?php

declare(strict_types=1);

function perfGetFilename(array $argv): string
{
    if (2 !== count($argv)) {
        echo 'Usage: '.basename((string) $argv[0])." filename\n";
        exit(1);
    }

    $filename = $argv[1];
    if (!is_readable($filename)) {
        echo 'File not found or not readable: '.$filename."\n";
        exit(1);
    }

    return $filename;
}

function perfPrintSuccess(string $filename, float $start, int $count): void
{
    echo '- Parsing file '.$filename.': '.(microtime(true) - $start)."s\n";
    echo '* Triples parsed: '.$count."\n";
    echo '* Memory usage: '.(memory_get_usage() / 1024 / 1024)."MB\n";
}

function perfPrintFailure(string $filename, float $start, Throwable $error): void
{
    echo '- Parsing file '.$filename.' failed after '.(microtime(true) - $start)."s\n";
    echo '* Error: '.$error->getMessage()."\n";
}
