<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\Common\Config\ProgramConfig;
use App\Common\Database\Primary\Users;
use App\Common\Exception\API_Exception;
use App\Common\Exception\AppException;
use App\Common\Packages\ReCaptcha\ReCaptcha;
use App\Common\Users\Log;
use App\Common\Users\UserEmailsPresets;
use App\Common\Validator;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;
use Comely\Utils\Security\Passwords;
use Comely\Utils\Security\PRNG;

/**
 * Class Signin
 * @package App\API\Controllers
 */
class Signin extends AbstractSessionAPIController
{
    /**
     * @throws API_Exception
     */
    public function sessionAPICallback(): void
    {
        if (!$this->apiAccess->signIn) {
            throw API_Exception::ControllerDisabled();
        }

        if ($this->apiSession->authUserId) {
            throw new API_Exception('ALREADY_LOGGED_IN');
        }
    }

    /**
     * @throws API_Exception
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        $timeStamp = time();
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Countries');
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Logs');
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Tally');
        Schema::Bind($db, 'App\Common\Database\Primary\MailsQueue');

        // ReCaptcha Validation
        if ($this->isReCaptchaRequired()) {
            try {
                $reCaptchaRes = $this->input()->get("reCaptchaRes");
                if (!$reCaptchaRes || !is_string($reCaptchaRes)) {
                    throw new API_Exception('RECAPTCHA_REQ');
                }

                $programConfig = ProgramConfig::getInstance();
                $reCaptchaSecret = $programConfig->reCaptchaPrv;
                if (!$reCaptchaSecret || !is_string($reCaptchaSecret)) {
                    throw new AppException('ReCaptcha secret was not available');
                }

                try {
                    ReCaptcha::Verify($reCaptchaSecret, $reCaptchaRes, $this->ipAddress);
                } catch (\Exception $e) {
                    throw new API_Exception('RECAPTCHA_FAILED');
                }
            } catch (API_Exception $e) {
                $e->setParam("reCaptchaRes");
                throw $e;
            }
        }

        // Login ID
        try {
            $loginId = trim(strval($this->input()->get("login")));
            if (!$loginId) {
                throw new API_Exception('LOGIN_ID_REQ');
            } elseif (!Validator::isASCII($loginId, "-_.@")) {
                throw new API_Exception('LOGIN_ID_INVALID');
            }

            try {
                $user = strpos($loginId, "@") ? Users::Email($loginId, true) : Users::Username($loginId, true);
            } catch (AppException $e) {
                if ($e->getCode() === AppException::MODEL_NOT_FOUND) {
                    throw new API_Exception('LOGIN_ID_UNKNOWN');
                }

                throw $e;
            }

            // User
            $user->validate(); // Validate Checksum
            $user->credentials(); // Decrypt Credentials
            $user->params(); // Decrypt Params
            $tally = $user->tally(); // Get user tally

            // User Status
            if (!in_array($user->status, ["active", "frozen"])) {
                throw new API_Exception('AUTH_USER_DISABLED');
            }

        } catch (API_Exception $e) {
            $e->setParam("login");
            throw $e;
        }

        // Password
        try {
            $password = trim(strval($this->input()->get("password")));
            if (!$password) {
                throw new API_Exception('PASSWORD_REQ');
            }

            if (!$user->credentials()->verifyPassword($password)) {
                throw new API_Exception('PASSWORD_INCORRECT');
            }
        } catch (API_Exception $e) {
            $e->setParam("password");
            throw $e;
        }

        // Sign In
        try {
            $db->beginTransaction();

            $apiHmacSecret = Passwords::Generate(16);

            $tally->lastLogin = $timeStamp;
            $user->timeStamp = $timeStamp;
            $user->set("authToken", $this->apiSession->token()->binary()->raw());
            $user->set("authApiHmac", $apiHmacSecret);
            $user->query()->update(function () {
                throw new AppException('Failed to update user row');
            });

            $user->log("signin", null, null, null, ["signin", "auth"]);
            $tally->save();

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw API_Exception::InternalError();
        }

        $user->deleteCached();

        // Authenticate Session
        $this->apiSession->loginAs($user);

        $this->status(true);
        $this->response()->set("username", $user->username);
        $this->response()->set("hasGoogle2FA", $user->credentials()->googleAuthSeed ? true : false);
        $this->response()->set("authHMACSecret", $apiHmacSecret);
    }
}
