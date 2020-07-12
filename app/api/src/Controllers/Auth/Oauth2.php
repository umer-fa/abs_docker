<?php
declare(strict_types=1);

namespace App\API\Controllers\Auth;

use App\Common\Exception\API_Exception;
use App\Common\Exception\AppException;
use App\Common\Users\Credentials;

/**
 * Class Oauth2
 * @package App\API\Controllers\Auth
 */
class Oauth2 extends AbstractAuthSessAPIController
{
    public const EXPLICIT_METHOD_NAMES = true;

    /** @var Credentials */
    private Credentials $credentials;

    /**
     * @throws AppException
     */
    public function authSessCallback(): void
    {
        $this->credentials = $this->authUser->credentials();
    }

    /**
     * @throws API_Exception
     * @throws AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    public function postConnect(): void
    {
        $vendor = trim(strval($this->input()->get("vendor")));
        $vendorName = $this->vendorGoodName($vendor);
        $userCredProp = $this->userCredProp($vendor);

        if ($this->credentials->oAuth->$userCredProp) {
            $this->response()->set("errorData", [$vendorName]);
            throw new API_Exception('OAUTH2_ALREADY_CONNECTED');
        }

        $oAuth2 = \App\API\OAuth2::Get($vendor);
        $hasReqParams = false;
        if ($this->input()->has("code") || $this->input()->has("access_token")) {
            $hasReqParams = true;
        }

        if (!$hasReqParams) {
            $this->status(true);
            $this->response()->set("redirect", $oAuth2->getAuthURL("-redirect-url-"));
            return;
        }

        $redirectURI = trim(strval($this->input()->get("redirectURI")));
        $profile = $oAuth2->requestProfile($this->input()->array(), $redirectURI);

        if ($profile->email !== $this->authUser->email) {
            $this->response()->set("errorData", [$vendorName]);
            throw new API_Exception('OAUTH2_EMAIL_MISMATCH');
        }

        // Save changes
        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $userCipher = $this->authUser->cipher();
            $this->credentials->oAuth->$userCredProp = $profile->id;
            $this->authUser->set("credentials", $userCipher->encrypt(clone $this->credentials)->raw());
            $this->authUser->timeStamp = time();
            $this->authUser->query()->update(function () {
                throw new API_Exception('Failed to update user row');
            });

            $this->authUser->log('oauth2-connect', [$vendorName], null, null, ["oauth2"]);

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw API_Exception::InternalError();
        }

        // Delete cached user instance
        $this->authUser->deleteCached();

        $this->status(true);
    }

    /**
     * @throws API_Exception
     * @throws AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    public function postDisconnect(): void
    {
        $vendor = trim(strval($this->input()->get("vendor")));
        $vendorName = $this->vendorGoodName($vendor);
        $userCredProp = $this->userCredProp($vendor);

        if (!$this->credentials->oAuth->$userCredProp) {
            $this->response()->set("errorData", [$vendorName]);
            throw new API_Exception('OAUTH2_NOT_CONNECTED');
        }

        // Save changes
        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $userCipher = $this->authUser->cipher();
            $this->credentials->oAuth->$userCredProp = null;
            $this->authUser->set("credentials", $userCipher->encrypt(clone $this->credentials)->raw());
            $this->authUser->timeStamp = time();
            $this->authUser->query()->update(function () {
                throw new API_Exception('Failed to update user row');
            });

            $this->authUser->log('oauth2-disconnect', [$vendorName], null, null, ["oauth2"]);

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw API_Exception::InternalError();
        }

        // Delete cached user instance
        $this->authUser->deleteCached();

        $this->status(true);
    }

    /**
     * @param string $vendor
     * @return string
     */
    private function vendorGoodName(string $vendor): string
    {
        switch (strtolower($vendor)) {
            case "google":
                return "Google";
            case "facebook":
                return "Facebook";
            case "linkedin":
                return "LinkedIn";
            default:
                return "OAuth2.0";
        }
    }

    /**
     * @param string $vendor
     * @return string
     * @throws AppException
     */
    private function userCredProp(string $vendor): string
    {
        $prop = null;
        switch (strtolower($vendor)) {
            case "google":
                $prop = "googleId";
                break;
            case "facebook":
                $prop = "facebookId";
                break;
            case "linkedin":
                $prop = "linkedInId";
                break;
        }

        if ($prop && property_exists($this->credentials->oAuth, $prop)) {
            return $prop;
        }

        throw new AppException('Failed to determine OAuth prop in User credential object');
    }

    /**
     * @return void
     */
    public function get(): void
    {
        $this->status(true);
        $this->response()->set("oauth2", $this->credentials->oAuth);
    }
}
