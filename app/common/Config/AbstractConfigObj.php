<?php
declare(strict_types=1);

namespace App\Common\Config;

use App\Common\Database\Primary\DataStore;
use App\Common\Exception\AppConfigException;
use App\Common\Exception\AppException;
use App\Common\Kernel;
use Comely\DataTypes\Buffer\Binary;
use Comely\Utils\Security\Exception\SecurityUtilException;

/**
 * Class AbstractConfigObj
 * @package App\Common\Config
 * @property int $cachedOn
 * @method void beforeSave()
 */
abstract class AbstractConfigObj
{
    /** @var null|string */
    public const DB_KEY = null;
    /** @var null|string */
    public const CACHE_KEY = null;
    /** @var null|int */
    public const CACHE_TTL = null;
    /** @var bool */
    public const IS_ENCRYPTED = false;

    /** @var array */
    private static array $instances = [];

    /**
     * @param bool $useCache
     * @return static
     * @throws AppException
     */
    public static function getInstance(bool $useCache = true): self
    {
        $instanceId = get_called_class();
        if (isset(static::$instances[$instanceId])) {
            return static::$instances[$instanceId];
        }

        $k = Kernel::getInstance();

        if ($useCache && is_string(static::CACHE_KEY)) {
            try {
                $cache = $k->cache();
                $configObject = $cache->get(static::CACHE_KEY);
            } catch (\Exception $e) {
            }
        }

        if (isset($configObject) && $configObject instanceof self) {
            static::$instances[$instanceId] = $configObject;
            return static::$instances[$instanceId];
        }

        if (!is_string(static::DB_KEY)) {
            throw new AppException('Invalid ConfigObject key');
        }

        // Fetch from database
        try {
            $db = $k->db()->primary();
            $query = $db->query()->table(DataStore::NAME)
                ->where('`key`=?', [static::DB_KEY])
                ->limit(1)
                ->fetch()
                ->first();

            if (!$query || !isset($query["data"]) || !is_string($query["data"])) {
                throw new AppException(sprintf('Config "%s" not found in DB', static::DB_KEY));
            }

            $bytes = new Binary($query["data"]);
            $configObject = static::IS_ENCRYPTED ?
                $k->ciphers()->project()->decrypt($bytes) : unserialize($bytes->raw());

            if (!$configObject instanceof self) {
                throw new AppException(sprintf('Failed to unserialize "%s" configuration object', static::DB_KEY));
            }
        } catch (AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $k->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(sprintf('Failed to retrieve "%s" configuration object', static::DB_KEY));
        }

        // Store in cache?
        if (isset($cache)) {
            try {
                $cloneConfig = clone $configObject;
                $cloneConfig->cachedOn = time();
                $cache->set(static::CACHE_KEY, $cloneConfig, static::CACHE_TTL);
            } catch (\Exception $e) {
                $k->errors()->triggerIfDebug($e, E_USER_WARNING);
                trigger_error(sprintf('Failed to store "%s" configuration object in cache', static::DB_KEY), E_USER_WARNING);
            }
        }

        static::$instances[$instanceId] = $configObject;
        return static::$instances[$instanceId];
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        return [get_called_class()];
    }

    /**
     * @param string $prop
     * @param $val
     * @param bool $checkUnInitProp
     * @return bool
     * @throws AppException
     */
    public function setValue(string $prop, $val, bool $checkUnInitProp = false): bool
    {
        if (!property_exists($this, $prop)) {
            throw new AppException(sprintf('Prop "%s" does not exist in class "%s"', $prop, get_called_class()));
        }

        if ($checkUnInitProp) {
            $change = true;
            if (isset($this->$prop)) {
                $change = $this->$prop !== $val;
            }

            if ($change) {
                $this->$prop = $val;
            }

            return $change;
        }

        if ($this->$prop !== $val) {
            $this->$prop = $val;
            return true;
        }

        return false;
    }

    /**
     * @throws AppException
     */
    public function save(): void
    {
        if (static::IS_ENCRYPTED) {
            try {
                if (method_exists($this, 'beforeSave')) {
                    $this->beforeSave();
                }

                $bytes = Kernel::getInstance()->ciphers()->project()->encrypt($this);
            } catch (AppConfigException|SecurityUtilException $e) {
                throw new AppException('Failed to encrypt configuration object');
            }
        } else {
            $bytes = new Binary(serialize($this));
        }

        DataStore::Save(static::DB_KEY, $bytes);
    }
}
