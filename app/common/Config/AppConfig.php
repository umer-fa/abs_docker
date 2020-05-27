<?php
declare(strict_types=1);

namespace App\Common\Config;

use App\Common\Config\AppConfig\DbCred;
use App\Common\Exception\ConfigException;
use Comely\Utils\Validator\Exception\ValidationException;
use Comely\Utils\Validator\Validator;
use Comely\Yaml\Yaml;

/**
 * Class AppConfig
 * @package App\Common\Config
 */
class AppConfig
{
    /** @var AppConfig|null */
    private static ?AppConfig $instance = null;

    public static function getInstance(): self
    {
        // Todo: Cached configuration file

        if (!static::$instance) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    /** @var string */
    private string $adminHost;
    /** @var int */
    private int $adminPort;
    /** @var string */
    private string $apiHost;
    /** @var int */
    private int $apiPort;
    /** @var string */
    private ?string $mysqlRootPassword = null;
    /** @var array */
    private array $dbs = [];

    private function __construct()
    {
        // Read ENV vars


        // Read YAML files
        $configPath = "../../config/";
        $dbConfig = Yaml::Parse($configPath . "databases.yml")->generate();
        $dbIndex = -1;
        foreach ($dbConfig as $label => $args) {
            $dbIndex++;

            try {
                $label = Validator::String($label)->lowerCase()->match('/^\w{3,32}$/')->validate();
            } catch (ValidationException $e) {
                throw new ConfigException(sprintf('DB[:%d]: [%s] Invalid label', $dbIndex, get_class($e)));
            }

            if (!is_array($args)) {
                throw new ConfigException(sprintf('DB[%s]: Value must be of type Object', $label));
            }

            $dbCred = new DbCred($label, $args);
            $this->dbs[$label] = $dbCred;
        }

        $cacheConfig = Yaml::Parse($configPath . "cache.yml")->generate();
        foreach ($cacheConfig as $label => $args) {

        }
    }

    /**
     * @param string $label
     * @return DbCred|null
     */
    public function db(string $label): ?DbCred
    {
        return $this->dbs[$label] ?? null;
    }

    /**
     * @return array
     */
    public function databases(): array
    {
        return $this->dbs;
    }

    /**
     * @return string|null
     */
    public function mysqlRootPassword(): ?string
    {
        return $this->mysqlRootPassword;
    }

    /**
     * @return array|string[]
     */
    public function __debugInfo(): array
    {
        return ["Private AppConfig Object"];
    }
}
