<?php
declare(strict_types=1);

namespace App\Common;

use App\Common\Config\AppConfig;
use App\Common\Exception\AppBootstrapException;
use App\Common\Kernel\AbstractErrorHandler;
use App\Common\Kernel\Databases;
use App\Common\Kernel\Directories;
use App\Common\Kernel\ErrorHandler\Errors;
use App\Common\Kernel\ErrorHandler\StdErrorHandler;
use Comely\Filesystem\Exception\PathNotExistException;

/**
 * Class Kernel
 * @package App\Common
 */
class Kernel
{
    /** @var Kernel|null */
    private static ?Kernel $instance = null;

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
    /** @var bool */
    private bool $debug;

    /**
     * Kernel constructor.
     */
    protected function __construct()
    {
        $this->debug = Validator::getBool(trim(getenv("COMELY_APP_DEBUG")));
        $this->dirs = new Directories();
        $this->dbs = new Databases();
        $this->errHandler = new StdErrorHandler($this);
        $this->errs = new Errors($this);


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
     * @return AppConfig
     */
    public function config(): AppConfig
    {
        if ($this->config) {
            return $this->config;
        }

        $cachedConfig = Validator::getBool(trim(getenv("COMELY_APP_CACHED_CONFIG")));
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
                return $this->config;
            }
        }

        $appConfig = new AppConfig();
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
        return $this->config;
    }
}
