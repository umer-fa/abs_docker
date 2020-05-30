<?php
declare(strict_types=1);

namespace App\Common\Config\AppConfig;

use App\Common\Exception\AppConfigException;
use Comely\Utils\Validator\Exception\InvalidValueException;
use Comely\Utils\Validator\Exception\ValidationException;
use Comely\Utils\Validator\Validator;

/**
 * Class CacheConfig
 * @package App\Common\Config\AppConfig
 */
class CacheConfig
{
    /** @var string|null */
    private ?string $engine = null;
    /** @var string */
    private string $host;
    /** @var int */
    private int $port;
    /** @var int */
    private int $timeOut;

    /**
     * CacheConfig constructor.
     * @param array $config
     * @throws AppConfigException
     */
    public function __construct(array $config)
    {
        try {
            $this->engine = Validator::String($config["engine"])->lowerCase()->nullable()->inArray(["redis", "memcached"])->validate();
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('Cache[engine]: %s', get_class($e)));
        }

        try {
            /** @var string $host */
            $host = Validator::String($config["host"])->validate(function (string $hostname) {
                $hostname = \App\Common\Validator::isValidHostname($hostname);
                if (!is_string($hostname)) {
                    throw new InvalidValueException();
                }

                return $hostname;
            });
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('Cache[host]: %s', get_class($e)));
        }

        $this->host = $host;

        try {
            /** @var int $port */
            $port = Validator::Integer($config["port"])->range(1000, 0xffff)->validate();
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('Cache[port]: %s', get_class($e)));
        }

        $this->port = $port;

        try {
            /** @var int $timeOut */
            $timeOut = Validator::Integer($config["time_out"])->range(1, 30)->validate();
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('Cache[time_out]: %s', get_class($e)));
        }

        $this->timeOut = $timeOut;
    }

    /**
     * @return array|string[]
     */
    public function __debugInfo(): array
    {
        return ["Private CacheConfig Object"];
    }

    /**
     * @return string|null
     */
    public function engine(): ?string
    {
        return $this->engine;
    }

    /**
     * @return string
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * @return int
     */
    public function timeOut(): int
    {
        return $this->timeOut;
    }
}
