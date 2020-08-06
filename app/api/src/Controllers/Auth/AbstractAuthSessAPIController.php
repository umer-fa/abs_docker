<?php
declare(strict_types=1);

namespace App\API\Controllers\Auth;

use App\API\Controllers\AbstractSessionAPIController;
use App\Common\Exception\API_Exception;
use App\Common\Exception\APIAuthException;
use App\Common\Exception\AppException;
use App\Common\Packages\GoogleAuth\GoogleAuthenticator;
use App\Common\Users\User;
use Comely\Database\Schema;
use Comely\Utils\Time\Time;

/**
 * Class AbstractAuthSessAPIController
 * @package App\API\Controllers\Auth
 */
abstract class AbstractAuthSessAPIController extends AbstractSessionAPIController
{
    /** @var User */
    protected User $authUser;

    /**
     * @throws APIAuthException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function sessionAPICallback(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Logs');
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Tally');

        // Signed in controllers are enabled?
        if (!$this->apiAccess->signIn) {
            throw API_Exception::ControllerDisabled();
        }

        $this->authUser = $this->apiSession->authenticate();
        $this->authUser->validate(); // Validate checksum
        $this->authUser->credentials(); // Decrypt credentials

        if (!$this->apiSession->authSessionOtp) {
            if ($this->authUser->credentials()->googleAuthSeed) {
                $byPassOTPControllers = [
                    'App\API\Controllers\Auth\Totp',
                    'App\API\Controllers\Auth\Signout',
                ];

                if (!in_array(get_called_class(), $byPassOTPControllers)) {
                    throw new APIAuthException('AUTH_USER_OTP');
                }
            }
        }

        // Validate SIGNATURE
        $this->validateUserSignature();

        // Callback
        $this->authSessCallback();
    }

    /**
     * @param array $excludeBodyParams
     * @throws API_Exception
     */
    private function validateUserSignature(array $excludeBodyParams = []): void
    {
        $userSecret = $this->authUser->private("authApiHmac");
        if (strlen($userSecret) !== 16) {
            throw new API_Exception('No secret value set for user HMAC');
        }

        // Prepare exclude vars
        $excludeBodyParams = array_map("strtolower", $excludeBodyParams);

        // Request params
        $payload = [];
        foreach ($this->input()->array() as $key => $value) {
            if (in_array(strtolower($key), $excludeBodyParams)) {
                $value = "";
            }

            $payload[$key] = $value;
        }

        $queryString = http_build_query($payload, "" ,"&", PHP_QUERY_RFC3986);

        // Calculate HMAC
        $hmac = hash_hmac("sha512", $queryString, $userSecret, false);
        if (!$hmac) {
            throw new API_Exception('Failed to generate cross-check HMAC signature');
        }

        if ($this->httpAuthHeader($this->app->constant("api_auth_header_user_sign")) !== $hmac) {
            throw new API_Exception('HMAC user signature validation failed');
        }

        // Timestamp
        $reqTimeStamp = (int)trim(strval($this->input()->get("timeStamp")));
        if (Time::difference($reqTimeStamp) >= 6) {
            $age = Time::difference($reqTimeStamp);
            throw new API_Exception(sprintf('The request query has expired, -%d seconds', $age));
        }

        // Nonce
        $reqNonce = trim(strval($this->input()->get("nonce")));
        if (!$reqNonce) {
            throw new API_Exception('Nonce value is required with auth API queries');
        }
    }

    /**
     * @param string $totp
     * @param string|null $param
     * @throws AppException
     */
    public function verifyTOTP(string $totp, ?string $param = "totp"): void
    {
        try {
            $tally = $this->authUser->tally();
            $googleAuthSeed = $this->authUser->credentials()->googleAuthSeed;
            if (!$googleAuthSeed) {
                throw new API_Exception('AUTH_USER_2FA_NOT_SETUP');
            }

            if (!$totp) {
                throw new API_Exception('2FA_TOTP_INVALID');
            }

            if (!preg_match('/^[0-9]{6}$/', $totp)) {
                throw new API_Exception('2FA_TOTP_INVALID');
            }

            if ($tally->last2faCode === $totp) {
                throw new API_Exception('2FA_TOTP_USED');
            }

            $google2FA = new GoogleAuthenticator($googleAuthSeed);
            if (!$google2FA->verify($totp)) {
                throw new API_Exception('2FA_TOTP_INCORRECT');
            }

            $tally->last2fa = time();
            $tally->last2faCode = $totp;
            $tally->save();
        } catch (AppException $e) {
            $e->setParam($param ?? "");
            throw $e;
        }
    }

    /**
     * @return void
     */
    abstract public function authSessCallback(): void;
}
