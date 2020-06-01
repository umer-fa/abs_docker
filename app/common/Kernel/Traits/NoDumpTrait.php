<?php
declare(strict_types=1);

namespace App\Common\Kernel\Traits;

/**
 * Trait NoDumpTrait
 * @package App\Common\Kernel\Traits
 */
trait NoDumpTrait
{
    /**
     * @return array
     */
    final public function __debugInfo()
    {
        return [get_called_class()];
    }
}
