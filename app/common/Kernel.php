<?php
declare(strict_types=1);

namespace App\Common;

use App\Common\Config\AppConfig;
use App\Common\Exception\AppBootstrapException;
use App\Common\Exception\AppConfigException;
use App\Common\Exception\AppDirException;
use App\Common\Exception\AppException;
use App\Common\Kernel\AbstractErrorHandler;
use App\Common\Kernel\Ciphers;
use App\Common\Kernel\Databases;
use App\Common\Kernel\Directories;
use App\Common\Kernel\ErrorHandler\Errors;
use App\Common\Kernel\ErrorHandler\StdErrorHandler;
use App\Common\Kernel\Memory;
use App\Common\Mailer\Mailer;
use Comely\Cache\Cache;
use Comely\Cache\Exception\CacheException;
use Comely\Filesystem\Exception\PathNotExistException;
use Comely\Filesystem\Filesystem;
use FurqanSiddiqui\SemaphoreEmulator\Exception\SemaphoreEmulatorException;
use FurqanSiddiqui\SemaphoreEmulator\SemaphoreEmulator;

/**
 * Class Kernel
 * @package App\Common
 */
class Kernel implements AppConstants
{
    /** @var Kernel|null */
    protected static ?Kernel $instance = null;

    /**
     * @return static
     */
    public static function getInstance(): self
    {
        if (!static::$instance) {
            throw new \UnexpectedValueException('App kernel not bootstrapped');
        }

        return static::$instance;
    }

    /**
     * @return static
     * @throws AppBootstrapException
     */
    public static function Bootstrap(): self
    {
        if (static::$instance) {
            throw new AppBootstrapException('App kernel is already bootstrapped');
        }

        return static::$instance = new static();
    }

    /** @var AppConfig */
    private AppConfig $config;
    /** @var Directories */
    private Directories $dirs;
    /** @var Databases */
    private Databases $dbs;
    /** @var AbstractErrorHandler */
    private AbstractErrorHandler $errHandler;
    /** @var Errors */
    private Errors $errs;
    /** @var Ciphers */
    private Ciphers $ciphers;
    /** @var Cache */
    private Cache $cache;
    /** @var bool */
    private bool $debug;
    /** @var Memory|null */
    private ?Memory $mem = null;
    /** @var SemaphoreEmulator|null */
    private ?SemaphoreEmulator $semaphore = null;
    /** @var string */
    private string $timeZone;

    /**
     * Kernel constructor.
     * @throws Exception\AppConfigException
     * @throws Exception\AppDirException
     * @throws \Comely\Yaml\Exception\ParserException
     */
    protected function __construct()
    {
        $this->debug = Validator::getBool(trim(strval(getenv("COMELY_APP_DEBUG"))));
        $this->dirs = new Directories();
        $this->dbs = new Databases();
        $this->errHandler = new StdErrorHandler($this);
        $this->errs = new Errors($this);
        $this->ciphers = new Ciphers($this);
        $this->setTimeZone(trim(strval(getenv("APP_TIMEZONE"))));

        $this->initConfig(Validator::getBool(trim(strval(getenv("COMELY_APP_CACHED_CONFIG")))));

        $this->cache = new Cache();
        $cacheConfig = $this->config->cache();
        if ($cacheConfig->engine()) {
            try {
                $this->cache->servers()->add(
                    $cacheConfig->engine(),
                    $cacheConfig->host(),
                    $cacheConfig->port(),
                    $cacheConfig->timeOut()
                );

                $this->cache->connect();
            } catch (CacheException $e) {
                $this->errors()->trigger($e, E_USER_WARNING);
            }
        }
    }

    /**
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->timeZone;
    }

    /**
     * @param string $tz
     * @return $this
     * @throws AppConfigException
     */
    public function setTimeZone(string $tz): self
    {
        if (!in_array($tz, \DateTimeZone::listIdentifiers())) {
            throw new AppConfigException('Invalid configured timezone');
        }

        $this->timeZone = $tz;
        date_default_timezone_set($this->timeZone);
        return $this;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param bool $bool
     * @return $this
     */
    public function setDebug(bool $bool): self
    {
        $this->debug = $bool;
        return $this;
    }

    /**
     * @param AbstractErrorHandler $eh
     */
    public function setErrorHandler(AbstractErrorHandler $eh): void
    {
        $this->errHandler = $eh;
    }

    /**
     * @return AbstractErrorHandler
     */
    public function errorHandler(): AbstractErrorHandler
    {
        return $this->errHandler;
    }

    /**
     * @return Errors
     */
    public function errors(): Errors
    {
        return $this->errs;
    }

    /**
     * @return Directories
     */
    public function dirs(): Directories
    {
        return $this->dirs;
    }

    /**
     * @return Databases
     */
    public function db(): Databases
    {
        return $this->dbs;
    }

    /**
     * @return Memory
     */
    public function memory(): Memory
    {
        if (!$this->mem) {
            $this->mem = new Memory();
            $this->mem->caching($this->cache);
        }

        return $this->mem;
    }

    /**
     * @return AppConfig
     */
    public function config(): AppConfig
    {
        return $this->config;
    }

    /**
     * @return Ciphers
     */
    public function ciphers(): Ciphers
    {
        return $this->ciphers;
    }

    /**
     * @return Cache
     */
    public function cache(): Cache
    {
        return $this->cache;
    }

    /**
     * @return SemaphoreEmulator
     * @throws AppException
     */
    public function semaphoreEmulator(): SemaphoreEmulator
    {
        if (!$this->semaphore) {
            try {
                $this->semaphore = new SemaphoreEmulator($this->dirs->sempahore());
            } catch (AppDirException|SemaphoreEmulatorException $e) {
                $this->errs->trigger($e, E_USER_WARNING);
                throw new AppException('Failed to get SemaphoreEmulator');
            }
        }

        return $this->semaphore;
    }

    /**
     * @return Mailer
     */
    public function mailer(): Mailer
    {
        return Mailer::getInstance();
    }

    /**
     * @param bool $cachedConfig
     * @throws Exception\AppConfigException
     * @throws Exception\AppDirException
     * @throws \Comely\Yaml\Exception\ParserException
     */
    private function initConfig(bool $cachedConfig): void
    {
        Filesystem::clearStatCache($this->dirs->tmp()->suffix("comely-appConfig.php.cache"));

        if ($cachedConfig) {
            try {
                $cachedConfigObj = $this->dirs->tmp()
                    ->file("comely-appConfig.php.cache", false)
                    ->read();
            } catch (PathNotExistException $e) {
            } catch (\Exception $e) {
                trigger_error('Failed to load cached configuration', E_USER_WARNING);
                if ($this->debug) {
                    Errors::Exception2Error($e, E_USER_WARNING);
                }
            }
        }

        if (isset($cachedConfigObj) && $cachedConfigObj) {
            $appConfig = unserialize($cachedConfigObj, [
                "allowed_classes" => [
                    'App\Common\Config\AppConfig',
                    'App\Common\Config\AppConfig\DbCred',
                    'App\Common\Config\AppConfig\CacheConfig',
                    'App\Common\Config\AppConfig\CipherKeys',
                    'App\Common\Config\AppConfig\PublicConfig',
                ]
            ]);

            if ($appConfig instanceof AppConfig) {
                $this->config = $appConfig;
            }
        }

        $appConfig = new AppConfig($this);
        if ($cachedConfig) {
            try {
                $this->dirs->tmp()
                    ->file("comely-appConfig.php.cache", true)
                    ->edit(serialize($appConfig), true);
            } catch (\Exception $e) {
                trigger_error('Failed to write cached configuration', E_USER_WARNING);
                if ($this->debug) {
                    Errors::Exception2Error($e, E_USER_WARNING);
                }
            }
        }

        $this->config = $appConfig;
    }

    /**
     * @param string $const
     * @return mixed
     */
    final public function constant(string $const)
    {
        return @constant('static::' . strtoupper($const));
    }
}
