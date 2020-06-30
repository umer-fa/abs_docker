<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Kernel\Memory\Query;
use App\Common\Kernel\Traits\NoDumpTrait;
use App\Common\Kernel\Traits\NotCloneableTrait;
use App\Common\Kernel\Traits\NotSerializableTrait;
use Comely\Cache\Cache;
use Comely\Cache\Exception\CacheException;

/**
 * Class Memory
 * @package App\Common\Kernel
 */
class Memory
{
    /** @var array */
    private array $objects;
    /** @var null|Cache */
    private ?Cache $cache = null;

    use NoDumpTrait;
    use NotCloneableTrait;
    use NotSerializableTrait;

    /**
     * Memory constructor.
     */
    public function __construct()
    {
        $this->objects = [];
    }

    /**
     * @param Cache $cache
     * @return Memory
     */
    public function caching(Cache $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @param string $key
     * @param string $instanceOf
     * @return Query
     */
    public function query(string $key, string $instanceOf): Query
    {
        return new Query($this, $key, $instanceOf);
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->objects = [];
    }

    /**
     * @param Query $query
     * @return bool|\Comely\Cache\CachedItem|float|int|mixed|string|null
     */
    public function get(Query $query)
    {
        $key = $query->key;
        $instanceOf = $query->instanceOf;
        $this->validateKey($key);

        // Check in run-time memory
        $object = $this->objects[$key] ?? null;
        if (is_object($object) && is_a($object, $instanceOf)) {
            return $object;
        }

        // Check in Cache
        if ($this->cache && $query->cache) {
            try {
                $cached = $this->cache->get($key, false);
                if (is_object($cached) && is_a($cached, $instanceOf)) {
                    $this->objects[$key] = $cached; // Store in run-time memory
                    return $cached;
                }
            } catch (CacheException $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        }

        // Not found, proceed with callback (if any)
        $callback = $query->callback;
        if (is_callable($callback)) {
            $object = call_user_func($callback);
            if (is_object($object)) {
                $this->set($key, $object, $query->cache, $query->cacheTTL);
                return $object;
            }
        }

        return null;
    }

    /**
     * @param string $key
     * @param $object
     * @param bool $cache
     * @param int $ttl
     */
    public function set(string $key, $object, bool $cache, int $ttl = 0): void
    {
        $this->validateKey($key); // Validate key

        // Is a instance?
        if (!is_object($object)) {
            throw new \UnexpectedValueException('Memory component may only store instances');
        }

        // Store in run-time memory
        $this->objects[$key] = $object;

        // Store in cache?
        if ($this->cache && $cache) {
            try {
                $this->cache->set($key, clone $object, $ttl);
            } catch (CacheException $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        }
    }

    /**
     * @param string $key
     */
    private function validateKey(string $key)
    {
        if (!preg_match('/^[\w\-.@+:]{3,128}$/i', $key)) {
            throw new \InvalidArgumentException('Invalid memory object key');
        }
    }
}
