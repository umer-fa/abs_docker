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
