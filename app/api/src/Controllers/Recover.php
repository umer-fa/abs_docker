<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\Common\Config\ProgramConfig;
use App\Common\Database\Primary\Users;
use App\Common\Exception\API_Exception;
use App\Common\Exception\AppException;
use App\Common\Packages\ReCaptcha\ReCaptcha;
use App\Common\Users\UserEmailsPresets;
use App\Common\Validator;
use Comely\Database\Schema;
use Comely\Utils\Security\PRNG;
use Comely\Utils\Time\Time;

/**
 * Class Recover
 * @package App\API\Controllers
 */
class Recover extends AbstractSessionAPIController
{
    /**
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function sessionAPICallback(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Countries');
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Logs');
    }

    /**
     * @throws API_Exception
     * @throws AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    public function postRequest(): void
    {
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

        // E-mail address
        try {
            $email = trim(strval($this->input()->get("email")));
            if (!$email) {
                throw new API_Exception('EMAIL_ADDR_REQ');
            } elseif (!Validator::isValidEmailAddress($email)) {
                throw new API_Exception('EMAIL_ADDR_INVALID');
            } elseif (strlen($email) > 64) {
                throw new API_Exception('EMAIL_ADDR_LEN');
            }

            try {
                $user = Users::Email($email);
                $user->validate();
                $user->credentials();
                $user->params();
                $user->tally();
            } catch (AppException $e) {
                if ($e->getCode() === AppException::MODEL_NOT_FOUND) {
                    throw new API_Exception('LOGIN_ID_UNKNOWN');
                }

                throw $e;
            }
        } catch (API_Exception $e) {
            $e->setParam("email");
            throw $e;
        }

        // Country
        try {
            $country = trim(strval($this->input()->get("country")));
            if (!$country) {
                throw new API_Exception('COUNTRY_INVALID');
            }

            $country = \App\Common\Database\Primary\Countries::get($country);
            if ($user->country !== $country->code) {
                throw new API_Exception('USER_MATCH_COUNTRY');
            }
        } catch (API_Exception $e) {
            $e->setParam("country");
            throw $e;
        }

        // Last requested on
        $lastRequestedOn = $user->tally()->lastReqRec;
        if (is_int($lastRequestedOn)) {
            $timeSinceLast = Time::difference($lastRequestedOn);
            if ($timeSinceLast < 900) {
                $this->response()->set("wait", ceil((900 - $timeSinceLast) / 60));
                throw new API_Exception('RECOVER_REQ_TIMEOUT');
            }
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            // Generate and same token
            $resetToken = PRNG::randomBytes(16)->base64()->value();
            $user->params()->resetTokenEpoch = time();
            $user->params()->resetToken = $resetToken;
            $user->set("params", $user->cipher()->encrypt(clone $user->params()));
            $user->query()->update(function () {
                throw new AppException('Failed to same user params');
            });

            // Update Tally
            $user->tally()->lastReqRec = time();

            // User Log
            $user->log('recovery-req', null, null, null, ["account"]);

            // Send e-mail message
            UserEmailsPresets::RecoveryRequest($user, $resetToken);

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

        $this->status(true);
    }
}
