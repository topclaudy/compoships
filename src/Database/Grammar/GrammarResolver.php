<?php

namespace Awobaz\Compoships\Database\Grammar;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use WeakMap;

/**
 * Chooses the query grammar for models using the package.
 *
 * The package grammar is installed only while the connection carries
 * Laravel's stock grammar for its driver; a grammar installed by the
 * application or another package is left untouched. One package grammar is
 * kept per connection instance so every builder on a connection shares it.
 */
final class GrammarResolver
{
    /** @var \WeakMap<\Illuminate\Database\Connection, array{stock: string, grammar: \Illuminate\Database\Query\Grammars\Grammar}>|null */
    private static ?WeakMap $grammars = null;

    private const DRIVERS = [
        'mysql'   => MySqlGrammar::class,
        'pgsql'   => PostgresGrammar::class,
        'sqlite'  => SQLiteGrammar::class,
        'sqlsrv'  => SqlServerGrammar::class,
        'mariadb' => MariaDbGrammar::class,
    ];

    public static function forConnection(Connection $connection): Grammar
    {
        self::$grammars ??= new WeakMap();

        $current = $connection->getQueryGrammar();

        if (isset(self::$grammars[$connection]) && get_class($current) === self::$grammars[$connection]['stock']) {
            return self::$grammars[$connection]['grammar'];
        }

        $packageClass = self::DRIVERS[$connection->getDriverName()] ?? null;

        if ($packageClass === null || get_class($current) !== get_parent_class($packageClass)) {
            return $current;
        }

        self::$grammars[$connection] = [
            'stock'   => get_class($current),
            'grammar' => new $packageClass($connection),
        ];

        return self::$grammars[$connection]['grammar'];
    }
}
