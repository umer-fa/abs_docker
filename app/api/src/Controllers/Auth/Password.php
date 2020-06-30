<?php
declare(strict_types=1);

namespace App\API\Controllers\Auth;

use App\Common\Exception\API_Exception;
use App\Common\Exception\AppException;
use Comely\Utils\Security\Passwords;

/**
 * Class Password
 * @package App\API\Controllers\Auth
 */
class Password extends AbstractAuthSessAPIController
{
    public const EXPLICIT_METHOD_NAMES = true;

    /**
     * @return void
     */
    public function authSessCallback(): void
    {
    }

    /**
     * @throws API_Exception
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        // Password
        try {
            $password = trim(strval($this->input()->get("newPassword")));
            $passwordLen = strlen($password);
            if (!$password) {
                throw new API_Exception('PASSWORD_REQ');
            } elseif ($passwordLen <= 5) {
                throw new API_Exception('PASSWORD_LEN_MIN');
            } elseif ($passwordLen > 32) {
                throw new API_Exception('PASSWORD_LEN_MAX');
            } elseif (Passwords::Strength($password) < 4) {
                throw new API_Exception('PASSWORD_WEAK');
            }

            if ($this->authUser->credentials()->verifyPassword($password)) {
                throw new API_Exception('PASSWORD_SAME');
            }
        } catch (AppException $e) {
            $e->setParam("newPassword");
            throw $e;
        }

        // Confirm Password
        try {
            $confirmPassword = trim(strval($this->input()->get("confirmPassword")));
            if ($password !== $confirmPassword) {
                throw new API_Exception('PASSWORD_CONFIRM_MATCH');
            }
        } catch (API_Exception $e) {
            $e->setParam("confirmPassword");
            throw $e;
        }

        // Existing Password
        try {
            $existingPassword = trim(strval($this->input()->get("currentPassword")));
            if (!$existingPassword || !$this->authUser->credentials()->verifyPassword($existingPassword)) {
                throw new API_Exception('PASSWORD_INCORRECT');
            }
        } catch (API_Exception $e) {
            $e->setParam("currentPassword");
            throw $e;
        }

        // Save changes
        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $credentials = $this->authUser->credentials();
            $credentials->hashPassword($password);

            $userCipher = $this->authUser->cipher();
            $this->authUser->set("credentials", $userCipher->encrypt(clone $credentials)->raw());
            $this->authUser->timeStamp = time();
            $this->authUser->query()->update(function () {
                throw new API_Exception('Failed to update user row');
            });

            $this->authUser->log('password-change', null, null, null, ["account"]);

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
}
