<?php
declare(strict_types=1);

namespace App\Common\Kernel\Memory;

use App\Common\Kernel\Memory;

/**
 * Class Query
 * @package App\Common\Kernel\Memory
 * @property-read bool $cache
 * @property-read int $cacheTTL
 * @property-read string $key
 * @property-read string $instanceOf
 * @property-read null|\Closure $callback
 */
class Query
{
    /** @var Memory */
    private Memory $memory;
    /** @var bool */
    private bool $cache;
    /** @var int */
    private int $cacheTTL;
    /** @var string */
    private string $key;
    /** @var string */
    private string $instanceOf;
    /** @var null|\Closure */
    private ?\Closure $callback = null;

    /**
     * Query constructor.
     * @param Memory $memory
     * @param string $key
     * @param string $instanceOf
     */
    public function __construct(Memory $memory, string $key, string $instanceOf)
    {
        $this->memory = $memory;
        $this->cache = false;
        $this->cacheTTL = 0;
        $this->key = $key;
        $this->instanceOf = $instanceOf;
    }

    /**
     * @param string $prop
     * @return mixed
     */
    public function __get(string $prop)
    {
        switch ($prop) {
            case "cache":
            case "cacheTTL":
            case "key":
            case "instanceOf":
            case "callback":
                return $this->$prop;
        }

        throw new \DomainException('Cannot read inaccessible property');
    }

    /**
     * @param int $ttl
     * @return Query
     */
    public function cache(int $ttl = 0): self
    {
        $this->cache = true;
        $this->cacheTTL = $ttl;
        return $this;
    }

    /**
     * @param \Closure $callback
     * @return Query
     */
    public function callback(\Closure $callback): self
    {
        if (is_callable($callback)) {
            $this->callback = $callback;
        }

        return $this;
    }

    /**
     * @param \Closure|null $callback
     * @return mixed
     */
    public function fetch(?\Closure $callback = null)
    {
        if ($callback) {
            $this->callback($callback);
        }

        return $this->memory->get($this);
    }
}
