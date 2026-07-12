<?php

declare(strict_types=1);

$_SERVER['APP_BASE_PATH'] ??= dirname(__DIR__);
$_SERVER['APP_PUBLIC_PATH'] ??= __DIR__;

require __DIR__ . '/../vendor/laravel/octane/bin/frankenphp-worker.php';
