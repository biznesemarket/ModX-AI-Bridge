<?php

declare(strict_types=1);

/**
 * Verifies that _build/build.php produces byte-identical transport packages.
 *
 * xPDO randomizes vehicle guids (md5(uniqid(rand(), true))) and embeds the build
 * time in every zip entry. The builder passes deterministic guids and normalizes
 * the archive, so two builds of the same sources must hash identically.
 *
 * Run inside the MODX container:
 *   MODX_ROOT=/var/www/html php scripts/verify-package-reproducibility.php
 */

$root = dirname(__DIR__);
$modxRoot = rtrim((string) (getenv('MODX_ROOT') ?: '/var/www/html'), '/\\');
if (!is_file($modxRoot . '/config.core.php')) {
    fwrite(STDERR, "Set MODX_ROOT to a real MODX installation (no config.core.php).\n");
    exit(1);
}

$epoch = (int) (getenv('SOURCE_DATE_EPOCH') ?: 315532800);
putenv('MODX_ROOT=' . $modxRoot);
putenv('SOURCE_DATE_EPOCH=' . $epoch);

$config = require $root . '/_build/config.inc.php';
$packageName = $config['name_lower'] . '-' . $config['version']
    . ($config['release'] !== '' ? '-' . $config['release'] : '');
$packagePath = $modxRoot . '/core/packages/' . $packageName . '.transport.zip';

$buildCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/_build/build.php');
$hashes = [];
for ($run = 1; $run <= 2; $run++) {
    passthru($buildCommand, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "Build run {$run} failed (exit {$exitCode}).\n");
        exit(2);
    }
    if (!is_file($packagePath)) {
        fwrite(STDERR, "Built package not found after run {$run}: {$packagePath}\n");
        exit(2);
    }
    $hashes[] = (string) hash_file('sha256', $packagePath);
}

if ($hashes[0] !== $hashes[1]) {
    fwrite(STDERR, "PACKAGE REPRODUCIBILITY: FAIL\n");
    fwrite(STDERR, "  run1: {$hashes[0]}\n  run2: {$hashes[1]}\n");
    exit(3);
}

fwrite(STDOUT, "PACKAGE REPRODUCIBILITY: PASS ({$packageName}, sha256 {$hashes[0]})\n");
