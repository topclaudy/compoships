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
            return array_map(function ($k) {
                return parent::getAttribute($k);
            }, $key);
        }

        return parent::getAttribute($key);
    }

    public function qualifyColumn($column)
    {
        if (is_array($column)) { //Check for multi-column relationship
            return array_map(function ($c) {
                if (Str::contains($c, '.')) {
                    return $c;
                }

                return $this->getTable().'.'.$c;
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

        switch ($connection->getDriverName()) {
            case 'mysql':
                $grammar = new MySqlGrammar($connection);
                break;
            case 'pgsql':
                $grammar = new PostgresGrammar($connection);
                break;
            case 'sqlite':
                $grammar = new SqliteGrammar($connection);
                break;
            case 'sqlsrv':
                $grammar = new SqlServerGrammar($connection);
                break;
            case 'mariadb':
                $grammar = new MariaDbGrammar($connection);
                break;
            default:
                $grammar = $connection->getQueryGrammar();
        }

        if (method_exists($grammar, 'setConnection')) {
            $grammar->setConnection($connection);
        }

        if (method_exists($connection, 'withTablePrefix')) {
            $grammar = $connection->withTablePrefix($grammar);
        }

        return new QueryBuilder($connection, $grammar, $connection->getPostProcessor());
    }
}
