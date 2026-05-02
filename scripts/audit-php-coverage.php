#!/usr/bin/env php
<?php

declare(strict_types=1);

$cloverPath = $argv[1] ?? 'build/logs/clover.xml';

if (! is_file($cloverPath)) {
    fwrite(STDERR, "Coverage file not found: {$cloverPath}\n");
    fwrite(STDERR, "Run: ddev composer test:coverage:clover\n");
    exit(2);
}

$xml = simplexml_load_file($cloverPath);

if (! $xml instanceof SimpleXMLElement) {
    fwrite(STDERR, "Could not parse Clover file: {$cloverPath}\n");
    exit(2);
}

$undercoveredClasses = [];
$uncoveredFiles = [];

foreach ($xml->project->file as $file) {
    $path = (string) $file['name'];
    $metrics = $file->metrics;

    if (! $metrics) {
        continue;
    }

    $statements = (int) $metrics['statements'];
    $coveredStatements = (int) $metrics['coveredstatements'];
    $methods = (int) $metrics['methods'];
    $coveredMethods = (int) $metrics['coveredmethods'];

    if ($statements > 0 && $coveredStatements === 0) {
        $uncoveredFiles[] = $path;
    }

    if ($methods > $coveredMethods) {
        $undercoveredClasses[] = [
            'path' => $path,
            'methods' => $methods,
            'coveredMethods' => $coveredMethods,
        ];
    }
}

echo "PHP coverage audit\n";
echo "==================\n";
echo 'Files with zero covered statements: ' . count($uncoveredFiles) . "\n";
echo 'Files with uncovered methods: ' . count($undercoveredClasses) . "\n\n";

if ($uncoveredFiles !== []) {
    echo "Zero-covered files:\n";
    foreach ($uncoveredFiles as $path) {
        echo "- {$path}\n";
    }
    echo "\n";
}

if ($undercoveredClasses !== []) {
    echo "Files with method gaps:\n";
    foreach ($undercoveredClasses as $class) {
        echo sprintf(
            "- %s (%d/%d methods covered)\n",
            $class['path'],
            $class['coveredMethods'],
            $class['methods'],
        );
    }
}

exit($undercoveredClasses === [] && $uncoveredFiles === [] ? 0 : 1);
