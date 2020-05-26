<?php
declare(strict_types=1);

namespace App\Common;

/**
 * Class Kernel
 * @package App\Common
 */
abstract class Kernel
{
    /** @var Kernel|null */
    private static ?Kernel $instance = null;

    /**
     * @return static
     */
    public static function getInstance(): self
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    private Databa
}
