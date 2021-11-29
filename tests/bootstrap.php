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

// NOTE: we enforce UTC timezone because since Laravel 7 it has changed behavior for datetime casting (it includes timezone)
date_default_timezone_set('UTC');

if (!function_exists('getLaravelVersion')) {
    function getLaravelVersion()
    {
        exec("composer show 'illuminate/database' | grep 'versions' | grep -o -E '\*\ .+' | cut -d' ' -f2 | cut -d',' -f1;", $output);
        $output = str_replace('v', '', isset($output[0]) ? $output[0] : '0.0');
        $version = explode('.', $output);

        if (!is_numeric($version[0])) {
            return 0.0;
        }

        return (float) "$version[0].$version[1]";
    }
}
if (!function_exists('getPHPVersion')) {
    function getPHPVersion()
    {
        $version = explode('.', PHP_VERSION);

        return (float) "$version[0].$version[1]";
    }
}
