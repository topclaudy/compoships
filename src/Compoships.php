<?php

namespace Awobaz\Compoships;

use Awobaz\Compoships\Database\Eloquent\Concerns\HasRelationships;
use Awobaz\Compoships\Database\Grammar\MariaDbGrammar;
use Awobaz\Compoships\Database\Grammar\MySqlGrammar;
use Awobaz\Compoships\Database\Grammar\PostgresGrammar;
use Awobaz\Compoships\Database\Grammar\SQLiteGrammar;
use Awobaz\Compoships\Database\Grammar\SqlServerGrammar;
use Awobaz\Compoships\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

trait Compoships
{
    use HasRelationships;

    public function getAttribute($key)
    {
        if (is_array($key)) { //Check for multi-columns relationship
            return array_map(fn ($k) => parent::getAttribute($k), $key);
        }

        return parent::getAttribute($key);
    }

    public function qualifyColumn($column)
    {
        if (is_array($column)) {
            return array_map(function ($c) {
                if (Str::contains($c, '.')) {
                    return $c;
                }

                $connection = $this->getConnection();
                $prefix = $connection->getTablePrefix();

                return $prefix.$this->getTable().'.'.$c;
            }, $column);
        }

        return parent::qualifyColumn($column);
    }

    /**
     * Configure Eloquent to use Compoships Query Builder.
     *
     * @return \Awobaz\Compoships\Database\Query\Builder|static
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        $grammar = match ($connection->getDriverName()) {
            'mysql'   => new MySqlGrammar($connection),
            'pgsql'   => new PostgresGrammar($connection),
            'sqlite'  => new SQLiteGrammar($connection),
            'sqlsrv'  => new SqlServerGrammar($connection),
            'mariadb' => new MariaDbGrammar($connection),
            default   => $connection->getQueryGrammar(),
        };

        if (method_exists($grammar, 'setConnection')) {
            $grammar->setConnection($connection);
        }

        if (method_exists($connection, 'withTablePrefix')) {
            $grammar = $connection->withTablePrefix($grammar);
        }

        return new QueryBuilder($connection, $grammar, $connection->getPostProcessor());
    }
}
