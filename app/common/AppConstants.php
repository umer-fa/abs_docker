<?php
declare(strict_types=1);

namespace App\Common;

/**
 * Interface AppConstants
 * @package App\Common
 */
interface AppConstants
{
    /** @var string App Name, Extending class should change these constant */
    public const NAME = "Comely App Kernel";
    /** string Comely App Kernel Version (Major.Minor.Release-Suffix) */
    public const VERSION = "2020.155";
    /** int Comely App Kernel Version (Major . Minor . Release) */
    public const VERSION_ID = 202015500;
    /** @var int[] */
    public const ROOT_ADMINISTRATORS = [1];
    /** @var string */
    public const API_AUTH_HEADER_SESS_TOKEN = "api-token";
    /** @var string */
    public const API_AUTH_HEADER_USER_SIGN = "user-signature";
}
