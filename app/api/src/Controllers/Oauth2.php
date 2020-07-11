<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\Common\Config\ProgramConfig;
use App\Common\Exception\API_Exception;
use App\Common\Exception\AppControllerException;

/**
 * Class Oauth2
 * @package App\API\Controllers
 */
class Oauth2 extends AbstractSessionAPIController
{
    /** @var ProgramConfig */
    private ProgramConfig $programConfig;

    /**
     * @throws API_Exception
     * @throws AppControllerException
     * @throws \App\Common\Exception\AppException
     */
    public function sessionAPICallback(): void
    {
        if (!$this->apiAccess->signIn) {
            throw API_Exception::ControllerDisabled();
        }

        if ($this->apiSession->authUserId) {
            throw new API_Exception('ALREADY_LOGGED_IN');
        }

        $this->programConfig = ProgramConfig::getInstance(true);
        if (!$this->programConfig->oAuthStatus) {
            throw new AppControllerException('OAuth2.0 has been disabled');
        }
    }

    /**
     * @throws AppControllerException
     */
    public function get(): void
    {
        $oAuthId = trim(strval($this->request()->url()->query()));
        $oAuthId = strtolower(strval(explode("=", explode("&", $oAuthId)[0])[0]));

        $oAuth2Vendor = null;
        switch ($oAuthId) {
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
            throw new AppControllerException('Invalid OAuth2.0 vendor');
        }

        $propStatus = sprintf('oAuth%s', $oAuth2Vendor);
        $propAppId = sprintf('oAuth%sAppId', $oAuth2Vendor);
        $propAppKey = sprintf('oAuth%sAppKey', $oAuth2Vendor);

        if (!property_exists($this->programConfig, $propStatus) || !$this->programConfig->$propStatus) {
            throw new AppControllerException(sprintf('OAuth2 via %s is currently disabled', $oAuth2Vendor));
        }

        $appId = property_exists($this->programConfig, $propAppId) ? $this->programConfig->$propAppId : false;
        $appKey = property_exists($this->programConfig, $propAppKey) ? $this->programConfig->$propAppKey : false;

        if (!$appId || !$appKey) {
            throw new AppControllerException(sprintf('%s OAuth2 App ID or Key is not configured', $oAuth2Vendor));
        }

        $oAuthClassname = sprintf('FurqanSiddiqui\OAuth2\Vendors\%1$s\%1$s', $oAuth2Vendor);
        if (!class_exists($oAuthClassname)) {
            throw new AppControllerException(sprintf('%s OAuth2 vendor class does not exist', $oAuth2Vendor));
        }

        if (!method_exists($oAuthClassname, "AuthenticateURL")) {
            throw new AppControllerException(sprintf('AuthenticateURL method does not exists in %s', $oAuth2Vendor));
        }

        $authURL = call_user_func_array([$oAuthClassname, "AuthenticateURL"], [$appId, "[redirect-url]"]);

        $this->status(true);
        $this->response()->set("redirect", $authURL);
    }
}
