<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\Common\Config\ProgramConfig;
use App\Common\Database\Primary\Users;
use App\Common\Exception\API_Exception;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Users\User;
use App\Common\Users\UserEmailsPresets;
use Comely\Database\Exception\ORM_ModelNotFoundException;

/**
 * Class Oauth2
 * @package App\API\Controllers
 */
class Oauth2 extends AbstractSessionAPIController
{
    /**
     * @throws API_Exception
     * @throws AppControllerException
     * @throws \App\Common\Exception\AppException
     */
    public function sessionAPICallback(): void
    {
        if ($this->apiSession->authUserId) {
            throw new API_Exception('ALREADY_LOGGED_IN');
        }

        $programConfig = ProgramConfig::getInstance(true);
        if (!$programConfig->oAuthStatus) {
            throw new AppControllerException('OAuth2.0 has been disabled');
        }
    }

    /**
     * @throws API_Exception
     * @throws AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    public function post(): void
    {
        $oAuthId = trim(strval($this->input()->get("vendor")));
        $oAuth = \App\API\OAuth2::Get($oAuthId);

        $redirectURI = trim(strval($this->input()->get("redirectURI")));
        $profile = $oAuth->requestProfile($this->input()->array(), $redirectURI);

        // Find user with e-mail address
        try {
            /** @var User $user */
            $user = Users::Find()->query('WHERE `email`=?', [$profile->email])->limit(1)->first();
            $user->validate();
        } catch (ORM_ModelNotFoundException $e) {
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e instanceof AppException ? $e->getMessage() : $e, E_USER_WARNING);
            throw new AppException('Failed to retrieve User');
        }

        if (isset($user) && $user) {
            $userCredOAuth = $user->credentials()->oAuth;
            $credProp = $this->getUserCredProp($oAuthId);
            if (!property_exists($userCredOAuth, $credProp) || !$userCredOAuth->$credProp || $userCredOAuth->$credProp !== $profile->id) {
                throw new AppException(sprintf('%s account not linked with your account profile', $oAuthId));
            }

            $this->oAuthSignIn($user, $oAuthId);

            $this->status(true);
            $this->response()->set("oAuthResult", "signin");
            $this->response()->set("username", $user->username);
            $this->response()->set("hasGoogle2FA", $user->credentials()->googleAuthSeed ? true : false);
            return;
        }
    }

    /**
     * @param User $user
     * @param string $oAuthId
     * @throws API_Exception
     * @throws AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    private function oAuthSignIn(User $user, string $oAuthId): void
    {
        $db = $this->app->db()->primary();
        $timeStamp = time();

        $tally = $user->tally();

        // Sign In
        try {
            $db->beginTransaction();

            $tally->lastLogin = $timeStamp;
            $user->timeStamp = $timeStamp;
            $user->set("authToken", $this->apiSession->token()->binary()->raw());
            $user->query()->update(function () {
                throw new AppException('Failed to update user row');
            });

            $user->log("oauth-signin", [$oAuthId], null, null, ["signin", "auth", "oauth"]);
            $tally->save();

            // Difference IP from last login
            if (isset($lastLoginLog)) {
                if ($lastLoginLog->ipAddress !== $this->ipAddress) {
                    UserEmailsPresets::SignInIPChange($user, $lastLoginLog->ipAddress, $this->ipAddress);
                }
            }

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
    }

    /**
     * @param string $vendor
     * @return string
     * @throws AppException
     */
    private function getUserCredProp(string $vendor): string
    {
        switch (strtolower($vendor)) {
            case "google":
                return "googleId";
            case "facebook":
                return "facebookId";
            case "linkedin":
                return "linkedInId";
        }

        throw new AppException('Failed to determine OAuth prop in User credential object');
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
