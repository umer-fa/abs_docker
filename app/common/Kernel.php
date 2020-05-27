<?php
declare(strict_types=1);

namespace App\Common;

use App\Common\Exception\AppBootstrapException;
use App\Common\Kernel\Databases;
use App\Common\Kernel\Directories;

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

    /**
     * Kernel constructor.
     */
    private function __construct()
    {
        $this->dirs = new Directories();
        $this->dbs = new Databases();
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
