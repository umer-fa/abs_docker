<?php
declare(strict_types=1);

namespace App\API\Controllers\Auth;

use App\Common\Exception\API_Exception;
use App\Common\Exception\AppException;
use App\Common\Packages\GoogleAuth\GoogleAuthenticator;

/**
 * Class Totp
 * @package App\API\Controllers\Auth
 */
class Totp extends AbstractAuthSessAPIController
{
    public const EXPLICIT_METHOD_NAMES = true;

    /**
     * @return void
     */
    public function authSessCallback(): void
    {
    }

    /**
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        if ($this->apiSession->authSessionOtp) {
            $this->status(true);
            return;
        }


        $this->verifyTOTP(trim(strval($this->input()->get("totp"))));
        if (!$this->apiSession->authSessionOtp) {
            $this->authUser->log("2fa-auth", null, null, null, ["auth", "2fa"]);
            $this->apiSession->markAuthSessionOTP();
        }

        $this->status(true);
    }

    /**
     * @throws API_Exception
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postSave(): void
    {
        // Suggested Seed
        try {
            $suggestedSeed = trim(strval($this->input()->get("suggestedSeed")));
            if (!$suggestedSeed) {
                throw new API_Exception('SUGGESTED_SEED_REQ');
            }

            $baggageSuggestedSeed = $this->apiSession->baggage()->get("suggestedSeed");
            if (!$baggageSuggestedSeed) {
                throw new API_Exception('SUGGESTED_SEED_BAD');
            } elseif (!preg_match('/^[a-z0-9]{16,64}$/i', $baggageSuggestedSeed)) {
                throw new API_Exception('SUGGESTED_SEED_BAD');
            } elseif ($baggageSuggestedSeed !== $suggestedSeed) {
                throw new API_Exception('SUGGESTED_SEED_BAD');
            }
        } catch (API_Exception $e) {
            $e->setParam("suggestedSeed");
            throw $e;
        }

        // New TOTP
        try {
            $newTotp = trim(strval($this->input()->get("newTotp")));
            if (!$newTotp) {
                throw new API_Exception('2FA_TOTP_INVALID');
            }

            if (!preg_match('/^[0-9]{6}$/', $newTotp)) {
                throw new API_Exception('2FA_TOTP_INVALID');
            }

            $google2FA = new GoogleAuthenticator($baggageSuggestedSeed);
            if (!$google2FA->verify($newTotp)) {
                throw new API_Exception('2FA_TOTP_INCORRECT');
            }
        } catch (AppException $e) {
            $e->setParam("newTotp");
            throw $e;
        }

        // Existing TOTP
        if ($this->authUser->credentials()->googleAuthSeed) {
            $this->verifyTOTP(trim(strval($this->input()->get("currentTotp"))), "currentTotp");
        }

        // Save changes?
        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $credentials = $this->authUser->credentials();
            $credentials->googleAuthSeed = $baggageSuggestedSeed;

            $userCipher = $this->authUser->cipher();
            $this->authUser->set("credentials", $userCipher->encrypt(clone $credentials)->raw());
            $this->authUser->timeStamp = time();
            $this->authUser->query()->update(function () {
                throw new API_Exception('Failed to update user row');
            });

            $this->authUser->log('2fa-setup', null, null, null, ["account", "2fa"]);

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

        // Delete from API session baggage
        try {
            $this->apiSession->baggage()->delete("suggestedSeed");
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
        }

        $this->status(true);
    }

    /**
     * @throws AppException
     */
    public function get(): void
    {
        $suggestedSeed = GoogleAuthenticator::generateSecret();

        $this->apiSession->baggage()
            ->set("suggestedSeed", $suggestedSeed);

        $this->status(true);
        $this->response()->set("suggestedSeed", $suggestedSeed);
    }
}
