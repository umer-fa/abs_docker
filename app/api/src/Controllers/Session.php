<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\Common\Config\ProgramConfig;
use App\Common\Database\API\Sessions;
use App\Common\Exception\API_Exception;
use App\Common\Exception\APIAuthException;
use Comely\Database\Schema;

/**
 * Class Session
 * @package App\API\Controllers
 */
class Session extends AbstractSessionAPIController
{
    public function sessionAPICallback(): void
    {
    }

    /**
     * @throws API_Exception
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        if ($this->apiSession) {
            throw new API_Exception('TOKEN_ALREADY_SUPPLIED');
        }

        $ipAddress = $this->app->http()->remote()->ipAddress ?? null;
        if (!$ipAddress) {
            throw new API_Exception('App failed to determine remote address');
        }

        // Determine token type
        $type = trim(strval($this->input()->get("type")));
        if (!in_array($type, Sessions::TOKEN_TYPES)) {
            throw new API_Exception('Invalid session token type');
        }

        // Issue Token
        $token = Sessions::issueToken($ipAddress, $type);
        $session = Sessions::getSession($token);
        $session->validate(); // Validate Session

        $this->status(true);
        $this->response()->set("token", $session->token()->hexits(false));
        //$this->response()->set("ip", $session->ipAddress);
    }

    /**
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function get(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Logs');

        $this->status(true);
        $this->response()->set("type", $this->apiSession->type);

        $authUserObject = null;
        $authSessionOtp = false;
        $authUserId = $this->apiSession->authUserId;
        if ($authUserId) {
            try {
                $authUser = $this->apiSession->authenticate();
                $authUserObject = [
                    "id" => $authUser->id,
                    "status" => $authUser->status,
                    "firstName" => $authUser->firstName,
                    "lastName" => $authUser->lastName,
                    "username" => $authUser->username,
                    "email" => $authUser->email,
                    "isEmailVerified" => $authUser->isEmailVerified === 1,
                    "hasGoogle2FA" => $authUser->credentials()->googleAuthSeed ? true : false,
                    "country" => $authUser->country,
                    "joinedStamp" => $authUser->joinStamp
                ];

                if ($this->apiSession->authSessionOtp === 1) {
                    $authSessionOtp = true;
                }
            } catch (APIAuthException $e) {
                $this->response()->set("authError", $e->getMessage());
            }
        }

        $this->response()->set("authUser", $authUserObject);
        $this->response()->set("authSessionOtp", $authSessionOtp);

        // ReCaptcha
        $reCaptcha = [
            "required" => false,
            "lastVerified" => $this->apiSession->recaptchaLast,
            "publicKey" => null
        ];

        $programConfig = ProgramConfig::getInstance(true);
        if ($programConfig->reCaptcha) {
            if ($programConfig->reCaptchaPub) {
                $reCaptcha["required"] = true;
                $reCaptcha["publicKey"] = $programConfig->reCaptchaPub;
            }
        }

        $this->response()->set("reCaptcha", $reCaptcha);
        $this->response()->set("issuedOn", $this->apiSession->issuedOn);
        $this->response()->set("lastUsedOn", $this->apiSession->lastUsedOn);

        // Baggage
        $baggage = [];

        $cfIPCountry = $this->request()->headers()->get("cf-ipcountry");
        if (is_string($cfIPCountry) && preg_match('/^[a-z]{2}$/i', $cfIPCountry)) {
            $baggage["cf-ipcountry"] = $cfIPCountry;
        }

        $this->response()->set("baggage", $baggage);
    }
}
