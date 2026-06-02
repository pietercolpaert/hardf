<?php

declare(strict_types=1);

$repoRoot = __DIR__;

$gitDir = $repoRoot.'/.git';
if (!is_dir($gitDir) && !is_file($gitDir)) {
    fwrite(STDOUT, "Skipping git hook installation: not a git checkout.\n");
    exit(0);
}

$hookPath = $repoRoot.'/.githooks/pre-commit';
if (!is_file($hookPath)) {
    fwrite(STDERR, "Could not find pre-commit hook at $hookPath\n");
    exit(1);
}

if (!@chmod($hookPath, 0755) && !is_executable($hookPath)) {
    fwrite(STDERR, "Could not make pre-commit hook executable.\n");
    exit(1);
}

$command = 'git -C '.escapeshellarg($repoRoot).' config core.hooksPath .githooks 2>&1';
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);

if (0 !== $exitCode) {
    fwrite(STDERR, "Failed to configure core.hooksPath:\n".implode("\n", $output)."\n");
    exit(1);
}

fwrite(STDOUT, "Configured git hooks at .githooks\n");