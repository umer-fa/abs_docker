<?php
declare(strict_types=1);

namespace App\Common\Config;

/**
 * Class APIServerAccess
 * @package App\Common\Config
 */
class APIServerAccess extends AbstractConfigObj
{
    public const DB_KEY = "app.apiServerAccess";
    public const CACHE_KEY = "app.apiServerAccess";
    public const CACHE_TTL = 86400;
    public const IS_ENCRYPTED = false;

    /** @var bool */
    public bool $globalStatus = false;
    /** @var bool */
    public bool $signUp = false;
    /** @var bool */
    public bool $signIn = false;
    /** @var bool */
    public bool $recoverPassword = false;
}
