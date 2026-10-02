<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Query\Builder;
use Awobaz\Compoships\Tests\Models\Allocation;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use PHPUnit\Framework\TestCase;

class OracleBuilderTest extends TestCase
{
    private $originalConnectionResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnectionResolver = Model::getConnectionResolver();
    }

    protected function tearDown(): void
    {
        if ($this->originalConnectionResolver === null) {
            Model::unsetConnectionResolver();
        } else {
            Model::setConnectionResolver($this->originalConnectionResolver);
        }

        parent::tearDown();
    }

    public function test_oracle_model_uses_compoships_builder_and_partitions_where_in(): void
    {
        $this->useOracleConnection();

        $query = (new Allocation())
            ->setConnection('oracle')
            ->newQuery()
            ->whereIn('id', range(1, 1001));

        $this->assertInstanceOf(Builder::class, $query->getQuery());

        $sql = strtoupper($query->toSql());

        $this->assertSame(2, substr_count($sql, ' IN ('));
        $this->assertStringContainsString(' OR ', $sql);
        $this->assertSame(range(1, 1001), $query->getBindings());
    }

    public function test_oracle_model_uses_and_between_chunked_where_not_in(): void
    {
        $this->useOracleConnection();

        $values = range(1, 1001);
        $query = (new Allocation())
            ->setConnection('oracle')
            ->newQuery()
            ->whereNotIn('id', $values);

        $this->assertInstanceOf(Builder::class, $query->getQuery());

        $sql = strtoupper($query->toSql());

        $this->assertSame(2, substr_count($sql, ' NOT IN ('));
        $this->assertStringContainsString(' AND ', $sql);
        $this->assertStringNotContainsString(' OR ', $sql);
        $this->assertSame($values, $query->getBindings());
    }

    public function test_oracle_does_not_split_exactly_1000_values(): void
    {
        $this->useOracleConnection();

        $values = range(1, 1000);
        $query = (new Allocation())
            ->setConnection('oracle')
            ->newQuery()
            ->whereIn('id', $values);

        $sql = strtoupper($query->toSql());

        $this->assertSame(1, substr_count($sql, ' IN ('));
        $this->assertSame($values, $query->getBindings());
    }

    public function test_sqlite_does_not_use_oracle_partitioning(): void
    {
        $this->useConnection('sqlite');

        $values = range(1, 1001);
        $query = (new Allocation())
            ->setConnection('sqlite')
            ->newQuery()
            ->whereIn('id', $values);

        $sql = strtoupper($query->toSql());

        $this->assertSame(1, substr_count($sql, ' IN ('));
        $this->assertStringNotContainsString(' OR ', $sql);
        $this->assertSame($values, $query->getBindings());
    }

    private function useOracleConnection(): void
    {
        $this->useConnection('oracle');
    }

    private function useConnection(string $driver): void
    {
        $connection = new class(null, $driver) extends Connection {
            public function __construct($pdo, private string $driver)
            {
                parent::__construct($pdo);
            }

            public function getDriverName()
            {
                return $this->driver;
            }
        };

        $grammar = new SQLiteGrammar($connection);
        $connection->setQueryGrammar($grammar);

        Model::setConnectionResolver(new ConnectionResolver([$driver => $connection]));
    }
}
