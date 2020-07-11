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
     * @throws \App\Common\Exception\AppException
     */
    public function post(): void
    {
        $oAuthId = trim(strval($this->input()->get("vendor")));
        $oAuth = \App\API\OAuth2::Get($oAuthId);

        $profile = $oAuth->requestProfile($this->input()->array(), "");
        var_dump($profile);
    }

    /**
     * @throws \App\Common\Exception\AppException
     */
    public function get(): void
    {
        $oAuthId = trim(strval($this->request()->url()->query()));
        $oAuthId = strtolower(strval(explode("=", explode("&", $oAuthId)[0])[0]));

        $oAuth = \App\API\OAuth2::Get($oAuthId);

        $this->status(true);
        $this->response()->set("redirect", $oAuth->getAuthURL("-redirect-url-"));
    }
}
