<?php
declare(strict_types=1);

namespace App\API;

use App\Common\Config\ProgramConfig;
use App\Common\Exception\AppException;
use FurqanSiddiqui\OAuth2\Vendors\AbstractVendor;
use FurqanSiddiqui\OAuth2\Vendors\Facebook\Facebook;
use FurqanSiddiqui\OAuth2\Vendors\Google\Google;
use FurqanSiddiqui\OAuth2\Vendors\LinkedIn\LinkedIn;

/**
 * Class OAuth2
 * @package App\API
 */
class OAuth2
{
    /**
     * @return Google
     * @throws AppException
     */
    public static function Google(): Google
    {
        /** @var Google $googleOAuth */
        $googleOAuth = static::Get("google");
        return $googleOAuth;
    }

    /**
     * @return LinkedIn
     * @throws AppException
     */
    public static function LinkedIn(): LinkedIn
    {
        /** @var LinkedIn $liOAuth */
        $liOAuth = static::Get("linkedIn");
        return $liOAuth;
    }

    /**
     * @return Facebook
     * @throws AppException
     */
    public static function Facebook(): Facebook
    {
        /** @var Facebook $fbOAuth */
        $fbOAuth = static::Get("facebook");
        return $fbOAuth;
    }

    /**
     * @param string $oAuthId
     * @return AbstractVendor
     * @throws AppException
     */
    public static function Get(string $oAuthId): AbstractVendor
    {
        $programConfig = ProgramConfig::getInstance(true);
        if (!$programConfig->oAuthStatus) {
            throw new AppException('OAuth2.0 has been disabled');
        }

        $oAuth2Vendor = null;
        switch (strtolower(strval($oAuthId))) {
            case "google":
                $oAuth2Vendor = "Google";
                break;
            case "linkedin":
                $oAuth2Vendor = "LinkedIn";
                break;
            case "facebook":
                $oAuth2Vendor = "Facebook";
                break;
        }

        if (!$oAuth2Vendor) {
            throw new AppException('Invalid OAuth2.0 vendor');
        }

        $propStatus = sprintf('oAuth%s', $oAuth2Vendor);
        $propAppId = sprintf('oAuth%sAppId', $oAuth2Vendor);
        $propAppKey = sprintf('oAuth%sAppKey', $oAuth2Vendor);

        if (!property_exists($programConfig, $propStatus) || !$programConfig->$propStatus) {
            throw new AppException(sprintf('OAuth2 via %s is currently disabled', $oAuth2Vendor));
        }

        $appId = property_exists($programConfig, $propAppId) ? $programConfig->$propAppId : false;
        $appKey = property_exists($programConfig, $propAppKey) ? $programConfig->$propAppKey : false;

        if (!$appId || !$appKey) {
            throw new AppException(sprintf('%s OAuth2 App ID or Key is not configured', $oAuth2Vendor));
        }

        $oAuthClassname = sprintf('FurqanSiddiqui\OAuth2\Vendors\%1$s\%1$s', $oAuth2Vendor);
        if (!class_exists($oAuthClassname)) {
            throw new AppException(sprintf('%s OAuth2 vendor class does not exist', $oAuth2Vendor));
        }

        return new $oAuthClassname($appId, $appKey);
    }
}
