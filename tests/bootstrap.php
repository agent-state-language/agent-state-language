<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap
 * 
 * This file is executed before running tests to set up the test environment.
 */

// Ensure we're using the correct timezone
date_default_timezone_set('UTC');

// Load Composer autoloader
$autoloader = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloader)) {
    echo "Composer autoloader not found. Please run 'composer install' first.\n";
    exit(1);
}

require_once $autoloader;

// Any additional test setup can go here
