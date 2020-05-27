<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Config\AppConfig;
use App\Common\Exception\ConfigException;
use Comely\Database\Database;
use Comely\Database\Server\DbCredentials;

/**
 * Class Databases
 * @package App\Common\Kernel
 */
class Databases
{
    /** @var array */
    private array $dbs = [];

    /**
     * @return Database
     * @throws ConfigException
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function primary(): Database
    {
        return $this->get("primary");
    }

    /**
     * @return Database
     * @throws ConfigException
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function apiLogs(): Database
    {
        return $this->get("api_logs");
    }

    /**
     * @param string $label
     * @return Database
     * @throws ConfigException
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function get(string $label = "primary"): Database
    {
        $label = strtolower($label);
        if (isset($this->dbs[$label])) {
            return $this->dbs[$label];
        }

        $appConfig = AppConfig::getInstance();
        $dbConfig = $appConfig->db($label);
        if (!$dbConfig) {
            throw new ConfigException(sprintf('Database "%s" is not configured', $label));
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
}
