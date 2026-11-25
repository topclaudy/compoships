<?php

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require dirname(__DIR__).'/vendor/autoload.php';

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
