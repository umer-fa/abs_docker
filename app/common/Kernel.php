<?php
declare(strict_types=1);

namespace App\Common;

use App\Common\Config\AppConfig;
use App\Common\Exception\AppBootstrapException;
use App\Common\Kernel\AbstractErrorHandler;
use App\Common\Kernel\Ciphers;
use App\Common\Kernel\Databases;
use App\Common\Kernel\Directories;
use App\Common\Kernel\ErrorHandler\Errors;
use App\Common\Kernel\ErrorHandler\StdErrorHandler;
use App\Common\Kernel\Memory;
use Comely\Filesystem\Exception\PathNotExistException;

/**
 * Class Kernel
 * @package App\Common
 */
class Kernel
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
    /** @var bool */
    private bool $debug;
    /** @var Memory|null */
    private ?Memory $mem = null;

    /**
     * Kernel constructor.
     * @throws Exception\AppConfigException
     * @throws Exception\AppDirException
     * @throws \Comely\Yaml\Exception\ParserException
     */
    protected function __construct()
    {
        $this->debug = Validator::getBool(trim(getenv("COMELY_APP_DEBUG")));
        $this->dirs = new Directories();
        $this->dbs = new Databases();
        $this->errHandler = new StdErrorHandler($this);
        $this->errs = new Errors($this);
        $this->ciphers = new Ciphers($this);

        $this->initConfig(Validator::getBool(trim(getenv("COMELY_APP_CACHED_CONFIG"))));
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
     * @param bool $cachedConfig
     * @throws Exception\AppConfigException
     * @throws Exception\AppDirException
     * @throws \Comely\Yaml\Exception\ParserException
     */
    private function initConfig(bool $cachedConfig): void
    {
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
}
