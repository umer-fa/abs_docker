<?php
declare(strict_types=1);

namespace App\API\Models;

use App\API\APIService;

/**
 * Class AbstractCachedObj
 * @package App\API\Models
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
     * @return $this
     */
    public function getInstance(string $instanceKey, bool $useCache = true): self
    {
        $app = APIService::getInstance();

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

        // Create new instance
        $instance = new static();

        // Store in cache?
        if (isset($cache)) {
            try {
                $cloneObject = clone $instance;
                $cloneObject->cachedOn = time();
                $cache->set($instanceKey, $cloneObject, static::CACHE_TTL);
            } catch (\Exception $e) {
                $app->errors()->triggerIfDebug($e, E_USER_WARNING);
                trigger_error(sprintf('Failed to store API model "%s" object in cache', $instanceKey), E_USER_WARNING);
            }
        }

        static::$instances[$instanceKey] = $instance;
        return static::$instances[$instanceKey];
    }
}
