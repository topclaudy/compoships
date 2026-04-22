<?php

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require dirname(__DIR__).'/vendor/autoload.php';

$autoloader->addClassMap([
    \Awobaz\Compoships\Tests\TestCase\TestCase::class => __DIR__.'/TestCase/TestCase8.php',
]);

date_default_timezone_set('UTC');
