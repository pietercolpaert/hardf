<?php

declare(strict_types=1);

const DEFAULT_SIZES = [10000, 100000, 1000000];
const QUICK_SIZES = [10000];

function usage(): void
{
    echo "Usage: benchmark-rdf.php [--quick] [statement-count ...]\n";
}

function parseArgs(array $argv): array
{
    $quick = false;
    $sizes = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ('--quick' === $arg) {
            $quick = true;
            continue;
        }
        if (!preg_match('/^\d+$/', $arg)) {
            usage();
            throw new InvalidArgumentException('Statement counts must be positive integers.');
        }
        $sizes[] = (int) $arg;
    }

    if (empty($sizes)) {
        $sizes = $quick ? QUICK_SIZES : DEFAULT_SIZES;
    }

    return $sizes;
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

function parsePerfOutput(string $output): array
{
    if (!preg_match('/Parsing file .*: ([0-9.]+)s/', $output, $timeMatch)) {
        throw new RuntimeException('Could not parse elapsed time from output: '.$output);
    }
    if (!preg_match('/Triples parsed: (\d+)/', $output, $countMatch)) {
        throw new RuntimeException('Could not parse statement count from output: '.$output);
    }

    return [
        'seconds' => (float) $timeMatch[1],
        'statements' => (int) $countMatch[1],
    ];
}

function printRow(string $dataset, string $parser, int $expected, array $result): void
{
    if ($expected !== $result['statements']) {
        throw new RuntimeException($parser.' parsed '.$result['statements'].' statements, expected '.$expected.'.');
    }
    $statementsPerSecond = $result['seconds'] > 0 ? $result['statements'] / $result['seconds'] : 0;
    printf(
        "| %s | %s | %s | %.2f | %.0f |\n",
        $dataset,
        $parser,
        number_format($result['statements']),
        $result['seconds'] * 1000,
        $statementsPerSecond
    );
}

try {
    $sizes = parseArgs($argv);
} catch (Throwable $error) {
    echo $error->getMessage()."\n";
    exit(1);
}

$generatedDir = rtrim(sys_get_temp_dir(), \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.'hardf-rdf-bench';
if (!is_dir($generatedDir) && !mkdir($generatedDir, 0777, true) && !is_dir($generatedDir)) {
    echo 'Could not create benchmark directory: '.$generatedDir."\n";
    exit(1);
}

$generateRdf12 = __DIR__.'/generate-rdf12-nq.php';
$generateNt = __DIR__.'/generate-nt.php';
$hardf = __DIR__.'/parser-streaming-perf.php';
$easyRdf = __DIR__.'/easyrdf-perf.php';
$arc2 = __DIR__.'/arc2-perf.php';

// Keep opcache/JIT settings explicit for reproducible CLI measurements.
$php = escapeshellarg(\PHP_BINARY).' -d '.escapeshellarg('opcache.enable_cli=1').' -d '.escapeshellarg('opcache.jit_buffer_size=0');

echo "| dataset | parser | statements | elapsed ms | statements/sec |\n";
echo "|---------|--------|-----------:|-----------:|---------------:|\n";

foreach ($sizes as $size) {
    $rdf12File = $generatedDir.'/rdf12-'.$size.'.nq';
    $rdf10File = $generatedDir.'/rdf10-'.$size.'.nt';

    try {
        runCommand($php.' '.escapeshellarg($generateRdf12).' '.escapeshellarg($rdf12File).' '.escapeshellarg((string) $size));
        runCommand($php.' '.escapeshellarg($generateNt).' '.escapeshellarg($rdf10File).' '.escapeshellarg((string) $size));

        printRow('RDF1.2 mixed N-Quads', 'Hardf strict', $size, parsePerfOutput(runCommand($php.' '.escapeshellarg($hardf).' '.escapeshellarg($rdf12File).' --format=N-Quads')));
        printRow('RDF1.2 mixed N-Quads', 'Hardf relax', $size, parsePerfOutput(runCommand($php.' '.escapeshellarg($hardf).' '.escapeshellarg($rdf12File).' --format=N-Quads --relax')));

        printRow('Plain RDF1.0 N-Triples', 'Hardf strict', $size, parsePerfOutput(runCommand($php.' '.escapeshellarg($hardf).' '.escapeshellarg($rdf10File).' --format=N-Triples')));
        printRow('Plain RDF1.0 N-Triples', 'EasyRDF', $size, parsePerfOutput(runCommand($php.' '.escapeshellarg($easyRdf).' '.escapeshellarg($rdf10File))));
        printRow('Plain RDF1.0 N-Triples', 'ARC2', $size, parsePerfOutput(runCommand($php.' '.escapeshellarg($arc2).' '.escapeshellarg($rdf10File))));
    } catch (Throwable $error) {
        echo 'Benchmark failed for '.$size.' statements: '.$error->getMessage()."\n";
        exit(1);
    } finally {
        if (is_file($rdf12File)) {
            unlink($rdf12File);
        }
        if (is_file($rdf10File)) {
            unlink($rdf10File);
        }
    }
}
