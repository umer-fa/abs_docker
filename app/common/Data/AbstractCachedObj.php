<?php
declare(strict_types=1);

namespace App\Common\Data;

use App\Common\Kernel;

/**
 * Class AbstractCachedObj
 * @package App\Common\Data
 * @property int $cachedOn
 */
abstract class AbstractCachedObj
{
    /** @var int */
    protected const CACHE_TTL = 21600;
    /** @var array */
    private static array $instances = [];

    /**
     * @param string $instanceKey
     * @param bool $useCache
     * @return static|null
     */
    public static function retrieveInstance(string $instanceKey, bool $useCache = true): ?self
    {
        $app = Kernel::getInstance();

        // In run-time memory?
        if (isset(static::$instances[$instanceKey])) {
            return static::$instances[$instanceKey];
        }

        // Use cache?
        if ($useCache) {
            try {
                $cache = $app->cache();
                $cachedObject = $cache->get($instanceKey);
            } catch (\Exception $e) {
            }
        }

        if (isset($cachedObject) && $cachedObject instanceof self) {
            static::$instances[$instanceKey] = $cachedObject;
            return static::$instances[$instanceKey];
        }

        return null;
    }

    /**
     * @param string $instanceKey
     * @param bool $useCache
     * @param array $constructorArgs
     * @return static
     */
    public static function createInstance(string $instanceKey, bool $useCache, array $constructorArgs): self
    {
        $app = Kernel::getInstance();

        // Create new instance
        $instanceClass = get_called_class();
        $instance = new $instanceClass(...$constructorArgs);

        // Store in cache?
        if ($useCache) {
            try {
                $cache = $app->cache();
            } catch (\Exception $e) {
            }

            if (isset($cache)) {
                try {
                    $cloneObject = clone $instance;
                    $cloneObject->cachedOn = time();
                    $cache->set($instanceKey, $cloneObject, static::CACHE_TTL);
                } catch (\Exception $e) {
                    $app->errors()->triggerIfDebug($e, E_USER_WARNING);
                    trigger_error(sprintf('Failed to store data model "%s" object in cache', $instanceKey), E_USER_WARNING);
                }
            }
        }

        static::$instances[$instanceKey] = $instance;
        return static::$instances[$instanceKey];
    }

    /**
     * @param string $instanceKey
     */
    public static function deleteCached(string $instanceKey): void
    {
        unset(static::$instances[$instanceKey]);

        try {
            Kernel::getInstance()->cache()->delete($instanceKey);
        } catch (\Exception $e) {
        }
    }
}
