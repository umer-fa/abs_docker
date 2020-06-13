<?php
declare(strict_types=1);

namespace App\Common\Config;

/**
 * Class ProgramConfig
 * @package App\Common\Config
 */
class ProgramConfig extends AbstractConfigObj
{
    public const DB_KEY = "app.programConfig";
    public const CACHE_KEY = "app.programConfig";
    public const CACHE_TTL = 86400;
    public const IS_ENCRYPTED = true;

    /** @var bool */
    public bool $reCaptcha = false;
    /** @var string|null */
    public ?string $reCaptchaPub = null;
    /** @var string|null */
    public ?string $reCaptchaPrv = null;

    /** @var bool */
    public bool $oAuthStatus = false;
    /** @var bool */
    public bool $oAuthFacebook = false;
    /** @var string|null */
    public ?string $oAuthFacebookAppId = null;
    /** @var string|null */
    public ?string $oAuthFacebookAppKey = null;
    /** @var bool */
    public bool $oAuthGoogle = false;
    /** @var string|null */
    public ?string $oAuthGoogleAppId = null;
    /** @var string|null */
    public ?string $oAuthGoogleAppKey = null;
    /** @var bool */
    public bool $oAuthLinkedIn = false;
    /** @var string|null */
    public ?string $oAuthLinkedInAppId = null;
    /** @var string|null */
    public ?string $oAuthLinkedInAppKey = null;
}
