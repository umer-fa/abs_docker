<?php
declare(strict_types=1);

namespace App\Common\Exception;

/**
 * Class XSRF_Exception
 * @package App\Common\Exception
 */
class XSRF_Exception extends AppControllerException
{
    public const TOKEN_MISMATCH = 0x0a;
    public const TOKEN_EXPIRED = 0x14;
    public const TOKEN_IP_MISMATCH = 0x1e;
    public const TOKEN_NOT_SET = 0x28;
}
