<?php
declare(strict_types=1);

namespace App\Common;

use App\Common\Exception\AppBootstrapException;
use App\Common\Kernel\AbstractErrorHandler;
use App\Common\Kernel\Databases;
use App\Common\Kernel\Directories;
use App\Common\Kernel\ErrorHandler\Errors;
use App\Common\Kernel\ErrorHandler\StdErrorHandler;

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
     * @throws AppBootstrapException
     */
    public static function getInstance(): self
    {
        if (!static::$instance) {
            throw new AppBootstrapException('App kernel not bootstrapped');
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
        $this->debug = Validator::getBool(getenv("COMELY_APP_DEBUG"));
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
}
