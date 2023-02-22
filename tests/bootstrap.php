<?php

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require dirname(__DIR__).'/vendor/autoload.php';

if (PHP_MAJOR_VERSION === 7 && in_array(PHP_MINOR_VERSION, [0, 1])) {
    $mappedTestCaseFilename = __DIR__.'/TestCase/TestCase6.php';
} else {
    $mappedTestCaseFilename = __DIR__.'/TestCase/TestCase8.php';
}

$autoloader->addClassMap([
    \Awobaz\Compoships\Tests\TestCase\TestCase::class => $mappedTestCaseFilename,
]);

// NOTE: we enforce UTC timezone because since Laravel 7 it has changed behavior for datetime casting (it includes timezone)
date_default_timezone_set('UTC');

if (!function_exists('getLaravelVersion')) {
    function getLaravelVersion()
    {
        return (float) \Composer\InstalledVersions::getVersion('illuminate/database');
    }
}
if (!function_exists('getPHPVersion')) {
    function getPHPVersion()
    {
        $version = explode('.', PHP_VERSION);

        return (float) "$version[0].$version[1]";
    }
}

if (getLaravelVersion() < 8.0) {
    class_alias(\Awobaz\Compoships\Tests\Factories\DumbHasFactory::class, '\Illuminate\Database\Eloquent\Factories\HasFactory');
}
