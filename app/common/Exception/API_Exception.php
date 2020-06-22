<?php
declare(strict_types=1);

namespace App\Common\Exception;

/**
 * Class API_Exception
 * @package App\Common\Exception
 */
class API_Exception extends AppControllerException
{
    /**
     * @return static
     */
    public static function InternalError(): self
    {
        return new self('INTERNAL_ERROR');
    }

    /**
     * @return static
     */
    public static function ControllerDisabled(): self
    {
        return new self('API_CONTROLLER_DISABLED');
    }
}
