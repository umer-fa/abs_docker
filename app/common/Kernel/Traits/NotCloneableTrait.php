<?php
declare(strict_types=1);

namespace App\Common\Kernel\Traits;

/**
 * Trait NotCloneableTrait
 * @package App\Common\Kernel\Traits
 */
trait NotCloneableTrait
{
    /**
     * Object cannot be cloned
     */
    final public function __clone()
    {
        throw new \RuntimeException(get_called_class() . ' instance cannot be cloned');
    }
}
