<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Kernel;
use Comely\Database\Database;
use Comely\Database\Queries\Query;
use Comely\Database\Server\DbCredentials;

/**
 * Class Databases
 * @package App\Common\Kernel
 */
class Databases
{
    /** @var string */
    public const PRIMARY = "primary";
    /** @var string */
    public const API_LOGS = "api_logs";

    use Kernel\Traits\NoDumpTrait;
    use Kernel\Traits\NotCloneableTrait;
    use Kernel\Traits\NotSerializableTrait;

    /** @var array */
    private array $dbs = [];

    /**
     * @param string $which
     * @return string|null
     */
    public function getDbName(string $which): ?string
    {
        $appConfig = Kernel::getInstance()->config();
        $dbConfig = $appConfig->db($which);
        if ($dbConfig) {
            return $dbConfig->name;
        }

        return null;
    }

    /**
     * @return Database
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function primary(): Database
    {
        return $this->get(self::PRIMARY);
    }

    /**
     * @return Database
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function apiLogs(): Database
    {
        return $this->get(self::API_LOGS);
    }

    /**
     * @param string $label
     * @return Database
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function get(string $label = self::PRIMARY): Database
    {
        $label = strtolower($label);
        if (isset($this->dbs[$label])) {
            return $this->dbs[$label];
        }

        $appConfig = Kernel::getInstance()->config();
        $dbConfig = $appConfig->db($label);
        if (!$dbConfig) {
            throw new \UnexpectedValueException(sprintf('Database "%s" is not configured', $label));
        }

        $dbCredentials = (new DbCredentials($dbConfig->driver))
            ->server($dbConfig->host, $dbConfig->port)
            ->database($dbConfig->name);

        if ($dbConfig->username) {
            $dbCredentials->credentials($dbConfig->username, $dbConfig->password ?? $appConfig->mysqlRootPassword());
        }

        $db = new Database($dbCredentials);
        $this->dbs[$label] = $db;
        return $db;
    }

    /**
     * @param string $name
     * @param Database $db
     */
    public function append(string $name, Database $db): void
    {
        $this->dbs[strtolower($name)] = $db;
    }

    /**
     * @return array
     */
    public function getAllQueries(): array
    {
        $queries = [];

        /**
         * @var string $dbName
         * @var Database $dbInstance
         */
        foreach ($this->dbs as $dbName => $dbInstance) {
            foreach ($dbInstance->queries() as $query) {
                $queries[] = [
                    "db" => $dbName,
                    "query" => $query
                ];
            }
        }

        return $queries;
    }

    /**
     * @return int
     */
    public function flushAllQueries(): int
    {
        $flushed = 0;

        /**
         * @var string $name
         * @var Database $db
         */
        foreach ($this->dbs as $name => $db) {
            $flushed += $db->queries()->count();
            $db->queries()->flush();
        }

        return $flushed;
    }
}
