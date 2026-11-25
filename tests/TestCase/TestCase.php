<?php

namespace Awobaz\Compoships\Tests\TestCase;

use Awobaz\Compoships\Tests\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->setupDatabase();
    }

    protected function setupDatabase()
    {
        Model::reguard();
        Carbon::setTestNow('2020-10-29 23:59:59');

        $capsuleManager = new Capsule();
        $capsuleManager->addConnection([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ]);
        $capsuleManager->setAsGlobal();
        $capsuleManager->bootEloquent();

        (new Migration())->up();
    }
}
