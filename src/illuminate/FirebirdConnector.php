<?php

namespace Firebird\Illuminate;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use InvalidArgumentException;
use PDO;

class FirebirdConnector extends Connector implements ConnectorInterface
{
    /**
     * Establish a database connection.
     *
     * @param  array  $config
     * @return \PDO
     */
    public function connect(array $config): PDO
    {
        return $this->createConnection(
            $this->getDsn($config),
            $config,
            $this->getOptions($config)
        );
    }

    /**
     * Create a DSN string from the configuration.
     *
     * @param  array  $config
     * @return string
     */
    protected function getDsn(array $config): string
    {
        $host     = $config['host'] ?? null;
        $database = $config['database'] ?? null;
        $port     = $config['port'] ?? null;
        $role     = $config['role'] ?? null;
        $charset  = $config['charset'] ?? null;

        if ($host === null || $database === null) {
            throw new InvalidArgumentException('Cannot connect to Firebird Database, no host or database supplied');
        }

        $dsn = "firebird:dbname={$host}";

        if ($port !== null) {
            $dsn .= "/{$port}";
        }

        $dsn .= ":{$database};";

        if ($role !== null) {
            $dsn .= "role={$role};";
        }

        if ($charset !== null) {
            $dsn .= "charset={$charset};";
        }

        return $dsn;
    }
}
