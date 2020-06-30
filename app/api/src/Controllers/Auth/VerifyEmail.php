<?php
declare(strict_types=1);

namespace App\API\Controllers\Auth;

use App\Common\Exception\API_Exception;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Users\UserEmailsPresets;
use Comely\Database\Schema;
use Comely\Utils\Time\Time;

/**
 * Class VerifyEmail
 * @package App\API\Controllers\Auth
 */
class VerifyEmail extends AbstractAuthSessAPIController
{
    public const EXPLICIT_METHOD_NAMES = true;

    /**
     * @throws AppControllerException
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function authSessCallback(): void
    {
        if ($this->authUser->isEmailVerified === 1) {
            throw new AppControllerException('EMAIL_ADDR_VERIFIED');
        }

        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\MailsQueue');
    }

    /**
     * @throws API_Exception
     * @throws AppException
     */
    public function postRequestResend(): void
    {
        $lastRequestedOn = $this->authUser->tally()->lastReqRec;
        if (is_int($lastRequestedOn)) {
            $timeSinceLast = Time::difference($lastRequestedOn);
            if ($timeSinceLast < 900) {
                $this->response()->set("wait", ceil((900 - $timeSinceLast) / 60));
                throw new API_Exception('EMAIL_VERIFY_REQ_TIMEOUT');
            }
        }

        try {
            UserEmailsPresets::EmailVerifyRequest($this->authUser);
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e instanceof AppException ? $e->getMessage() : $e, E_USER_WARNING);
            throw new API_Exception('Failed to send verification e-mail message');
        }

        $this->authUser->tally()->lastReqRec = time();
        $this->authUser->tally()->save();

        $this->status(true);
    }

    /**
     * @throws API_Exception
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postVerify(): void
    {
        $verificationCode = trim(strval($this->input()->get("code")));
        try {
            if (!$verificationCode) {
                throw new API_Exception('VERIFY_CODE_REQ');
            } elseif (!preg_match('/^[a-f0-9]{16}$/i', $verificationCode)) {
                throw new API_Exception('VERIFY_CODE_INVALID');
            } elseif ($verificationCode !== substr($this->authUser->emailVerifyBytes()->base16()->hexits(), -16)) {
                throw new API_Exception('VERIFY_CODE_INVALID');
            }
        } catch (API_Exception $e) {
            $e->setParam("code");
            throw $e;
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $this->authUser->isEmailVerified = 1;
            $this->authUser->timeStamp = time();
            $this->authUser->set("checksum", $this->authUser->checksum()->raw());
            $this->authUser->query()->update(function () {
                throw new AppControllerException('Failed to update user row');
            });

            $this->authUser->log('em-verified', null, null, null, ["account", "verification"]);

            // Send e-mail message
            UserEmailsPresets::EmailVerified($this->authUser);

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw API_Exception::InternalError();
        }

        $this->authUser->deleteCached();

        $this->status(true);
    }
}
