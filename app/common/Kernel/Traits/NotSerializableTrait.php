<?php
declare(strict_types=1);

namespace App\Common\Kernel\Traits;

/**
 * Trait NotSerializableTrait
 * @package App\Common\Kernel\Traits
 */
trait NotSerializableTrait
{
    final public function __sleep()
    {
        throw new \RuntimeException(get_called_class() . ' instance cannot be serialized');
    }

    final public function serialize()
    {
        throw new \RuntimeException(get_called_class() . ' instance cannot be serialized');
    }

    final public function __wakeup()
    {
        throw new \RuntimeException(get_called_class() . ' instance cannot be un-serialized');
    }

    final public function unserialize($serialized)
    {
        unset($serialized); // Just so my IDE stop giving unused param warning
        throw new \RuntimeException(get_called_class() . ' instance cannot be un-serialized');
    }
}
