<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Compoships;
use Awobaz\Compoships\Database\Grammar\SQLiteGrammar as PackageSQLiteGrammar;
use Awobaz\Compoships\Database\Query\Builder;
use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\TrackingTask;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\SQLiteGrammar as StockSQLiteGrammar;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Compoships::class)]
#[CoversClass(Builder::class)]
class GrammarInstallationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    public function test_stock_grammar_is_replaced_by_the_package_grammar_once_per_connection()
    {
        $this->assertInstanceOf(StockSQLiteGrammar::class, Capsule::connection()->getQueryGrammar());

        $first = Allocation::query()->getQuery()->getGrammar();
        $second = TrackingTask::query()->getQuery()->getGrammar();

        $this->assertInstanceOf(PackageSQLiteGrammar::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_a_custom_grammar_on_the_connection_is_preserved()
    {
        $connection = Capsule::connection();
        $custom = new class($connection) extends StockSQLiteGrammar {
        };
        $connection->setQueryGrammar($custom);

        try {
            $this->assertSame($custom, Allocation::query()->getQuery()->getGrammar());
            $this->assertSame($custom, $connection->query()->getGrammar());
        } finally {
            $connection->useDefaultQueryGrammar();
        }
    }

    public function test_composite_eager_limit_under_a_custom_grammar_fails_with_guidance()
    {
        $connection = Capsule::connection();
        $custom = new class($connection) extends StockSQLiteGrammar {
        };
        $connection->setQueryGrammar($custom);

        try {
            Allocation::create(['booking_id' => 1, 'vehicle_id' => 2]);

            $this->expectException(InvalidUsageException::class);
            $this->expectExceptionMessage(get_class($custom));

            Allocation::with(['trackingTasks' => fn ($q) => $q->limit(2)])->get();
        } finally {
            $connection->useDefaultQueryGrammar();
        }
    }

    public function test_composite_eager_limit_works_with_the_package_grammar()
    {
        $allocation = Allocation::create(['booking_id' => 1, 'vehicle_id' => 2]);
        $allocation->trackingTasks()->createMany([[], [], []]);

        $loaded = Allocation::with(['trackingTasks' => fn ($q) => $q->limit(2)])->first();

        $this->assertCount(2, $loaded->trackingTasks);
    }

    public function test_resolver_installs_a_driver_matching_grammar_for_every_supported_driver()
    {
        $pdo = new \PDO('sqlite::memory:');
        $cases = [
            'mysql'   => [new \Illuminate\Database\MySqlConnection($pdo, '', '', ['driver' => 'mysql']), \Illuminate\Database\Query\Grammars\MySqlGrammar::class],
            'mariadb' => [new \Illuminate\Database\MariaDbConnection($pdo, '', '', ['driver' => 'mariadb']), \Illuminate\Database\Query\Grammars\MariaDbGrammar::class],
            'pgsql'   => [new \Illuminate\Database\PostgresConnection($pdo, '', '', ['driver' => 'pgsql']), \Illuminate\Database\Query\Grammars\PostgresGrammar::class],
            'sqlsrv'  => [new \Illuminate\Database\SqlServerConnection($pdo, '', '', ['driver' => 'sqlsrv']), \Illuminate\Database\Query\Grammars\SqlServerGrammar::class],
            'sqlite'  => [new \Illuminate\Database\SQLiteConnection($pdo, '', '', ['driver' => 'sqlite']), \Illuminate\Database\Query\Grammars\SQLiteGrammar::class],
        ];

        foreach ($cases as $driver => [$connection, $stockClass]) {
            $grammar = \Awobaz\Compoships\Database\Grammar\GrammarResolver::forConnection($connection);

            $this->assertInstanceOf($stockClass, $grammar, $driver);
            $this->assertStringStartsWith('Awobaz\\Compoships\\Database\\Grammar\\', get_class($grammar), $driver);
            $this->assertContains(\Awobaz\Compoships\Database\Grammar\Concerns\CompileRowNumber::class, class_uses_recursive($grammar), $driver);
        }
    }
}
