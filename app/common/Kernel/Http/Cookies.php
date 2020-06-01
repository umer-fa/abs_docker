<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http;

/**
 * Class Cookies
 * @package App\Common\Kernel\Http
 */
class Cookies
{
    /** @var int */
    private int $expire;
    /** @var string */
    private string $path;
    /** @var string */
    private string $domain;
    /** @var bool */
    private bool $secure;
    /** @var bool */
    private bool $httpOnly;

    /**
     * Cookies constructor.
     */
    public function __construct()
    {
        $this->expire = 604800; // 1 week
        $this->path = "/";
        $this->domain = "";
        $this->secure = true;
        $this->httpOnly = true;
    }

    /**
     * @param int $seconds
     * @return $this
     */
    public function expire(int $seconds): self
    {
        $this->expire = $seconds;
        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function path(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @param string $domain
     * @return $this
     */
    public function domain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * @param bool $https
     * @return $this
     */
    public function secure(bool $https): self
    {
        $this->secure = $https;
        return $this;
    }

    /**
     * @param bool $httpOnly
     * @return $this
     */
    public function httpOnly(bool $httpOnly): self
    {
        $this->httpOnly = $httpOnly;
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return bool
     */
    public function set(string $name, string $value): bool
    {
        return setcookie(
            $name,
            $value,
            time() + $this->expire,
            $this->path,
            $this->domain,
            $this->secure,
            $this->httpOnly
        );
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function get(string $name): ?string
    {
        return $_COOKIE[$name] ?? null;
    }
}
