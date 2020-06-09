<?php
declare(strict_types=1);

namespace App\Common\Config;

/**
 * Class APIServerAccess
 * @package App\Common\Config
 */
class APIServerAccess extends AbstractConfigObj
{
    /** @var bool */
    public bool $globalStatus = false;
    /** @var bool */
    public bool $signUp = false;
    /** @var bool */
    public bool $signIn = false;
    /** @var bool */
    public bool $recoverPassword = false;
}
