<?php

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require_once dirname(__DIR__).'/vendor/autoload.php';

if (PHP_MAJOR_VERSION === 7 && in_array(PHP_MINOR_VERSION, [0, 1])) {
    $mappedTestCaseFilename = __DIR__.'/TestCase/TestCase6.php';
} else {
    $mappedTestCaseFilename = __DIR__.'/TestCase/TestCase8.php';
}

$autoloader->addClassMap([
    \Awobaz\Compoships\Tests\TestCase\TestCase::class => $mappedTestCaseFilename,
]);
