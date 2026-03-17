#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo trim(file_get_contents(__DIR__.'/bootstrap/cache/config.php')) ?: '';

$key = Illuminate\Encryption\Encrypter::generateKey('AES-256-CBC');

echo 'base64:' . base64_encode($key) . PHP_EOL;
