<?php

declare(strict_types=1);

const DEFAULT_SIZES = [100000, 1000000, 10000000];

function formatUsage(): void
{
    echo "Usage: compare-hardf-n3js.php [--all] [triple-count ...]\n";
    echo "  --all    Also benchmark EasyRDF and ARC2 (slower)\n";
    echo "Example: php perf/compare-hardf-n3js.php 100000 1000000 10000000\n";
    echo "Example: php perf/compare-hardf-n3js.php --all 100000\n";
}

function parseArgs(array $argv): array
{
    $sizes = [];
    $all = false;

    foreach (array_slice($argv, 1) as $arg) {
        if ('--all' === $arg) {
            $all = true;
            continue;
        }
        if (!preg_match('/^\d+$/', $arg)) {
            formatUsage();
            throw new InvalidArgumentException('Triple counts must be positive integers.');
        }
        $size = (int) $arg;
        if ($size <= 0) {
            formatUsage();
            throw new InvalidArgumentException('Triple counts must be positive integers.');
        }
        $sizes[] = $size;
    }

    if (empty($sizes)) {
        $sizes = DEFAULT_SIZES;
    }

    return ['sizes' => $sizes, 'all' => $all];
}

function ensureNodeAvailable(): void
{
    $output = [];
    $exitCode = 0;
    exec('command -v node', $output, $exitCode);
    if (0 !== $exitCode || empty($output)) {
        throw new RuntimeException('Could not find the node executable in PATH.');
    }
}

function slowerThanN3js(float $frameworkSeconds, float $n3jsSeconds): float
{
    return $frameworkSeconds / $n3jsSeconds;
}

function runCommand(string $command): string
{
    $output = [];
    $exitCode = 0;
    exec($command.' 2>&1', $output, $exitCode);
    $result = implode("\n", $output);

    if (0 !== $exitCode) {
        throw new RuntimeException($result);
    }

    return $result;
}

function parseBenchmarkOutput(string $output): array
{
    if (!preg_match('/Parsing file .*: ([0-9.]+)s/', $output, $timeMatch)) {
        throw new RuntimeException('Could not parse benchmark time from output: '.$output);
    }
    if (!preg_match('/Triples parsed: (\d+)/', $output, $countMatch)) {
        throw new RuntimeException('Could not parse benchmark triple count from output: '.$output);
    }
    if (!preg_match('/Memory usage: ([0-9.]+)MB/', $output, $memoryMatch)) {
        throw new RuntimeException('Could not parse benchmark memory usage from output: '.$output);
    }

    return [
        'seconds' => (float) $timeMatch[1],
        'triples' => (int) $countMatch[1],
        'memoryMb' => (float) $memoryMatch[1],
    ];
}

function printResults(array $results, bool $all): void
{
    if ($all) {
        echo "\n| triples | Hardf no opcache (ms) | Hardf no opcache (MB) | Hardf no opcache vs N3.js | Hardf opcache (ms) | Hardf opcache (MB) | Hardf opcache vs N3.js | EasyRDF opcache (ms) | EasyRDF opcache (MB) | EasyRDF vs N3.js | ARC2 opcache (ms) | ARC2 opcache (MB) | ARC2 vs N3.js | N3.js (ms) | N3.js (MB) |\n";
        echo "|--------:|----------------------:|----------------------:|--------------------------:|-------------------:|-------------------:|-----------------------:|---------------------:|---------------------:|-----------------:|------------------:|------------------:|---------------:|----------:|-----------:|\n";
    } else {
        echo "\n| triples | Hardf no opcache (ms) | Hardf no opcache (MB) | Hardf no opcache vs N3.js | Hardf opcache (ms) | Hardf opcache (MB) | Hardf opcache vs N3.js | N3.js (ms) | N3.js (MB) |\n";
        echo "|--------:|----------------------:|----------------------:|--------------------------:|-------------------:|-------------------:|-----------------------:|----------:|-----------:|\n";
    }

    foreach ($results as $row) {
        $hardfNoOpcacheSlower = slowerThanN3js($row['hardfNoOpcache']['seconds'], $row['n3js']['seconds']);
        $hardfOpcacheSlower = slowerThanN3js($row['hardfOpcache']['seconds'], $row['n3js']['seconds']);
        if ($all) {
            $easyRdfSlower = slowerThanN3js($row['easyRdfOpcache']['seconds'], $row['n3js']['seconds']);
            $arc2Slower = slowerThanN3js($row['arc2Opcache']['seconds'], $row['n3js']['seconds']);
            printf(
                "| %s | %.0f | %.3f | %.2fx | %.0f | %.3f | %.2fx | %.0f | %.3f | %.2fx | %.0f | %.3f | %.2fx | %.0f | %.3f |\n",
                number_format($row['triples']),
                $row['hardfNoOpcache']['seconds'] * 1000,
                $row['hardfNoOpcache']['memoryMb'],
                $hardfNoOpcacheSlower,
                $row['hardfOpcache']['seconds'] * 1000,
                $row['hardfOpcache']['memoryMb'],
                $hardfOpcacheSlower,
                $row['easyRdfOpcache']['seconds'] * 1000,
                $row['easyRdfOpcache']['memoryMb'],
                $easyRdfSlower,
                $row['arc2Opcache']['seconds'] * 1000,
                $row['arc2Opcache']['memoryMb'],
                $arc2Slower,
                $row['n3js']['seconds'] * 1000,
                $row['n3js']['memoryMb']
            );
        } else {
            printf(
                "| %s | %.0f | %.3f | %.2fx | %.0f | %.3f | %.2fx | %.0f | %.3f |\n",
                number_format($row['triples']),
                $row['hardfNoOpcache']['seconds'] * 1000,
                $row['hardfNoOpcache']['memoryMb'],
                $hardfNoOpcacheSlower,
                $row['hardfOpcache']['seconds'] * 1000,
                $row['hardfOpcache']['memoryMb'],
                $hardfOpcacheSlower,
                $row['n3js']['seconds'] * 1000,
                $row['n3js']['memoryMb']
            );
        }
    }
}

$generator = __DIR__.'/generate-nt.php';
$hardfBench = __DIR__.'/parser-streaming-perf.php';
$easyRdfBench = __DIR__.'/easyrdf-perf.php';
$arc2Bench = __DIR__.'/arc2-perf.php';
$n3Bench = __DIR__.'/n3js-perf.js';
$generatedDir = rtrim(sys_get_temp_dir(), \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.'hardf-perf';

try {
    $args = parseArgs($argv);
    $sizes = $args['sizes'];
    $all = $args['all'];
    ensureNodeAvailable();
} catch (Throwable $error) {
    echo $error->getMessage()."\n";
    exit(1);
}

if (!is_dir($generatedDir) && !mkdir($generatedDir, 0777, true) && !is_dir($generatedDir)) {
    echo 'Could not create benchmark directory: '.$generatedDir."\n";
    exit(1);
}

$results = [];

foreach ($sizes as $size) {
    $filename = $generatedDir.'/synthetic-'.$size.'.nt';
    echo 'Generating '.$size.' triples at '.$filename."\n";

    try {
        runCommand(implode(' ', [
            escapeshellarg(\PHP_BINARY),
            escapeshellarg($generator),
            escapeshellarg($filename),
            escapeshellarg((string) $size),
        ]));

        $hardfOutput = runCommand(implode(' ', [
            escapeshellarg(\PHP_BINARY),
            '-d',
            escapeshellarg('opcache.enable_cli=0'),
            escapeshellarg($hardfBench),
            escapeshellarg($filename),
        ]));

        $hardfOpcacheOutput = runCommand(implode(' ', [
            escapeshellarg(\PHP_BINARY),
            '-d',
            escapeshellarg('opcache.enable_cli=1'),
            escapeshellarg($hardfBench),
            escapeshellarg($filename),
        ]));

        $easyRdfOpcache = null;
        $arc2Opcache = null;

        if ($all) {
            $easyRdfOutput = runCommand(implode(' ', [
                escapeshellarg(\PHP_BINARY),
                '-d',
                escapeshellarg('opcache.enable_cli=1'),
                escapeshellarg($easyRdfBench),
                escapeshellarg($filename),
            ]));

            $arc2Output = runCommand(implode(' ', [
                escapeshellarg(\PHP_BINARY),
                '-d',
                escapeshellarg('opcache.enable_cli=1'),
                escapeshellarg($arc2Bench),
                escapeshellarg($filename),
            ]));

            $easyRdfOpcache = parseBenchmarkOutput($easyRdfOutput);
            $arc2Opcache = parseBenchmarkOutput($arc2Output);
        }

        $n3Output = runCommand(implode(' ', [
            'node',
            escapeshellarg($n3Bench),
            escapeshellarg($filename),
        ]));

        $results[] = [
            'triples' => $size,
            'hardfNoOpcache' => parseBenchmarkOutput($hardfOutput),
            'hardfOpcache' => parseBenchmarkOutput($hardfOpcacheOutput),
            'easyRdfOpcache' => $easyRdfOpcache,
            'arc2Opcache' => $arc2Opcache,
            'n3js' => parseBenchmarkOutput($n3Output),
        ];
    } catch (Throwable $error) {
        echo 'Benchmark failed for '.$size.' triples: '.$error->getMessage()."\n";
        exit(1);
    } finally {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
}

printResults($results, $all);
