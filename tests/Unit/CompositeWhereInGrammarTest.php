<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Query\Builder;
use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Database\SqlServerConnection;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Compiles composite predicates through each driver's grammar without
 * executing them, so SQL Server, MySQL and PostgreSQL output is pinned even
 * though CI only runs SQLite.
 */
#[CoversClass(Builder::class)]
class CompositeWhereInGrammarTest extends TestCase
{
    private function connection(string $driver, string $prefix = ''): Connection
    {
        $pdo = new PDO('sqlite::memory:');
        $config = ['driver' => $driver];

        return match ($driver) {
            'sqlsrv' => new SqlServerConnection($pdo, '', $prefix, $config),
            'mysql'  => new MySqlConnection($pdo, '', $prefix, $config),
            'pgsql'  => new PostgresConnection($pdo, '', $prefix, $config),
            default  => new SQLiteConnection($pdo, '', $prefix, $config),
        };
    }

    private function builder(string $driver, string $prefix = ''): Builder
    {
        $connection = $this->connection($driver, $prefix);

        return (new Builder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor()))->from('users');
    }

    public function test_sql_server_compiles_an_exact_tuple_predicate()
    {
        $query = $this->builder('sqlsrv')->whereIn(['id', 'tenant_id'], [[1, 2], [3, 4]]);

        $this->assertSame(
            'select * from [users] where (([id] = ? and [tenant_id] = ?) or ([id] = ? and [tenant_id] = ?))',
            $query->toSql()
        );
        $this->assertSame([1, 2, 3, 4], $query->getBindings());
    }

    public function test_sql_server_not_in_negates_the_group()
    {
        $query = $this->builder('sqlsrv')->whereNotIn(['id', 'tenant_id'], [[1, 2]]);

        $this->assertSame('select * from [users] where not (([id] = ? and [tenant_id] = ?))', $query->toSql());
        $this->assertSame([1, 2], $query->getBindings());
    }

    public function test_sql_server_or_boolean_wraps_the_group()
    {
        $query = $this->builder('sqlsrv')->where('x', 1)->orWhereIn(['id', 'tenant_id'], [[1, 2]]);

        $this->assertSame('select * from [users] where [x] = ? or (([id] = ? and [tenant_id] = ?))', $query->toSql());
        $this->assertSame([1, 1, 2], $query->getBindings());
    }

    public function test_sql_server_qualified_columns_are_prefixed()
    {
        $query = $this->builder('sqlsrv', 'pfx_')->whereIn(['users.id', 'users.tenant_id'], [[1, 2]]);

        $this->assertSame(
            'select * from [pfx_users] where (([pfx_users].[id] = ? and [pfx_users].[tenant_id] = ?))',
            $query->toSql()
        );
    }

    public function test_unqualified_columns_are_not_prefixed_on_tuple_capable_drivers()
    {
        foreach (['sqlite' => '"', 'mysql' => '`', 'pgsql' => '"'] as $driver => $quote) {
            $sql = $this->builder($driver, 'pfx_')->whereIn(['id', 'tenant_id'], [['u1', 't1']])->toSql();

            $this->assertStringContainsString("({$quote}id{$quote}, {$quote}tenant_id{$quote}) IN ((?, ?))", $sql, $driver);
            $this->assertStringNotContainsString('pfx_id', $sql, $driver);
            $this->assertStringContainsString("{$quote}pfx_users{$quote}", $sql, $driver);
        }
    }

    public function test_qualified_columns_are_prefixed_once_on_tuple_capable_drivers()
    {
        $sql = $this->builder('sqlite', 'pfx_')->whereIn(['users.id', 'users.tenant_id'], [['u1', 't1']])->toSql();

        $this->assertSame('select * from "pfx_users" where ("pfx_users"."id", "pfx_users"."tenant_id") IN ((?, ?))', $sql);
    }

    public function test_expression_column_is_emitted_verbatim()
    {
        $sql = $this->builder('sqlite')->whereIn([new Expression('lower(code)'), 'tenant_id'], [['a', 1]])->toSql();

        $this->assertSame('select * from "users" where (lower(code), "tenant_id") IN ((?, ?))', $sql);
    }

    public function test_sql_server_expression_column_is_emitted_verbatim()
    {
        $sql = $this->builder('sqlsrv')->whereIn([new Expression('lower(code)'), 'tenant_id'], [['a', 1]])->toSql();

        $this->assertSame('select * from [users] where ((lower(code) = ? and [tenant_id] = ?))', $sql);
    }

    public function test_empty_collection_matches_no_rows()
    {
        $sql = $this->builder('sqlite')->whereIn(['a', 'b'], collect([]))->toSql();

        $this->assertSame('select * from "users" where 0 = 1', $sql);
    }

    public function test_collection_of_tuples_compiles_like_an_array()
    {
        $fromCollection = $this->builder('sqlite')->whereIn(['a', 'b'], collect([[1, 2]]));
        $fromArray = $this->builder('sqlite')->whereIn(['a', 'b'], [[1, 2]]);

        $this->assertSame($fromArray->toSql(), $fromCollection->toSql());
        $this->assertSame($fromArray->getBindings(), $fromCollection->getBindings());
    }

    public function test_closure_value_compiles_a_tuple_subselect()
    {
        $query = $this->builder('sqlite')->whereIn(['a', 'b'], fn ($q) => $q->select('x', 'y')->from('t')->where('z', 1));

        $this->assertSame('select * from "users" where ("a", "b") IN (select "x", "y" from "t" where "z" = ?)', $query->toSql());
        $this->assertSame([1], $query->getBindings());
    }

    public function test_builder_value_compiles_a_tuple_subselect()
    {
        $sub = $this->builder('sqlite')->select('x', 'y')->from('t');
        $query = $this->builder('sqlite')->whereNotIn(['a', 'b'], $sub);

        $this->assertSame('select * from "users" where ("a", "b") NOT IN (select "x", "y" from "t")', $query->toSql());
    }

    public function test_arity_error_message_is_bounded_and_names_the_arity()
    {
        try {
            $this->builder('sqlite')->whereIn(['a', 'b'], [[1]]);
            $this->fail('expected InvalidUsageException');
        } catch (\Awobaz\Compoships\Exceptions\InvalidUsageException $e) {
            $this->assertStringContainsString('arity 2', $e->getMessage());
            $this->assertLessThan(500, strlen($e->getMessage()));
        }

        try {
            $this->builder('sqlite')->whereIn(['a', 'b'], [$this->builder('sqlite')]);
            $this->fail('expected InvalidUsageException');
        } catch (\Awobaz\Compoships\Exceptions\InvalidUsageException $e) {
            $this->assertLessThan(500, strlen($e->getMessage()));
        }
    }

    public function test_composite_where_column_groups_its_pairs_under_the_caller_boolean()
    {
        $query = $this->builder('sqlite')->where('x', 1)->orWhereColumn(['a', 'b'], ['c', 'd']);

        $this->assertSame('select * from "users" where "x" = ? or ("a" = "c" and "b" = "d")', $query->toSql());
    }

    public function test_composite_where_column_with_an_operator()
    {
        $query = $this->builder('sqlite')->whereColumn(['a', 'b'], '>=', ['c', 'd']);

        $this->assertSame('select * from "users" where ("a" >= "c" and "b" >= "d")', $query->toSql());
    }

    public function test_composite_where_column_rejects_mismatched_lengths()
    {
        $this->expectException(\Awobaz\Compoships\Exceptions\InvalidUsageException::class);

        $this->builder('sqlite')->whereColumn(['a', 'b'], ['c']);
    }

    public function test_sql_server_row_number_with_a_composite_partition_keeps_the_ordering_fallback()
    {
        $connection = $this->connection('sqlsrv');
        $grammar = \Awobaz\Compoships\Database\Grammar\GrammarResolver::forConnection($connection);
        $method = new \ReflectionMethod($grammar, 'compileRowNumber');

        $sql = $method->invoke($grammar, ['a', 'b'], '');

        $this->assertSame(', row_number() over (partition by [a], [b] order by (select 0)) as [laravel_row]', $sql);
        $this->assertSame(
            ', row_number() over (partition by [a], [b] order by [c] asc) as [laravel_row]',
            $method->invoke($grammar, ['a', 'b'], 'order by [c] asc')
        );
    }
}
