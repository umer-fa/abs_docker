<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\Common\Config\ProgramConfig;
use App\Common\Database\Primary\Users;
use App\Common\Exception\API_Exception;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Packages\ReCaptcha\ReCaptcha;
use App\Common\Users\Credentials;
use App\Common\Users\Params;
use App\Common\Users\User;
use App\Common\Users\UserEmailsPresets;
use App\Common\Validator;
use Comely\Database\Schema;
use Comely\DataTypes\Integers;
use Comely\Utils\Security\Passwords;

/**
 * Class Signup
 * @package App\API\Controllers
 */
class Signup extends AbstractSessionAPIController
{
    /**
     * @throws API_Exception
     */
    public function sessionAPICallback(): void
    {
        if (!$this->apiAccess->signUp) {
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
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\MailsQueue');
        Schema::Bind($db, 'App\Common\Database\Primary\Countries');
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Logs');

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

        // First name
        try {
            $firstName = trim(strval($this->input()->get("firstName")));
            if (!$firstName) {
                throw new API_Exception('FIRST_NAME_REQ');
            } elseif (!Integers::Range(strlen($firstName), 3, 16)) {
                throw new API_Exception('FIRST_NAME_LEN');
            } elseif (!preg_match('/^[a-z]+(\s[a-z]+)*$/i', $firstName)) {
                throw new API_Exception('FIRST_NAME_INVALID');
            }
        } catch (AppException $e) {
            $e->setParam("firstName");
            throw $e;
        }

        // Last name
        try {
            $lastName = trim(strval($this->input()->get("lastName")));
            if (!$lastName) {
                throw new API_Exception('LAST_NAME_REQ');
            } elseif (!Integers::Range(strlen($lastName), 2, 16)) {
                throw new API_Exception('LAST_NAME_LEN');
            } elseif (!preg_match('/^[a-z]+(\s[a-z]+)*$/i', $lastName)) {
                throw new API_Exception('LAST_NAME_INVALID');
            }
        } catch (AppException $e) {
            $e->setParam("lastName");
            throw $e;
        }

        // E-mail Address
        try {
            $email = trim(strval($this->input()->get("email")));
            if (!$email) {
                throw new API_Exception('EMAIL_ADDR_REQ');
            } elseif (!Validator::isValidEmailAddress($email)) {
                throw new API_Exception('EMAIL_ADDR_INVALID');
            } elseif (strlen($email) > 64) {
                throw new API_Exception('EMAIL_ADDR_LEN');
            }

            // Duplicate check
            $dup = $db->query()->table(Users::NAME)
                ->where('`email`=?', [$email])
                ->fetch();
            if ($dup->count()) {
                throw new API_Exception('EMAIL_ADDR_DUP');
            }
        } catch (AppException $e) {
            $e->setParam("email");
            throw $e;
        }

        // Country
        try {
            $country = trim(strval($this->input()->get("country")));
            if (strlen($country) !== 3) {
                throw new API_Exception('COUNTRY_INVALID');
            }

            try {
                $country = \App\Common\Database\Primary\Countries::get($country);
            } catch (AppException $e) {
                throw new API_Exception('COUNTRY_INVALID');
            }

            if ($country->status !== 1) {
                throw new API_Exception('COUNTRY_INVALID');
            }
        } catch (API_Exception $e) {
            $e->setParam("country");
            throw $e;
        }

        // Username
        try {
            $username = trim(strval($this->input()->get("username")));
            $usernameLen = strlen($username);
            if ($usernameLen <= 4) {
                throw new API_Exception('USERNAME_LEN_MIN');
            } elseif ($usernameLen >= 20) {
                throw new API_Exception('USERNAME_LEN_MAX');
            } elseif (!Validator::isValidUsername($username)) {
                throw new API_Exception('USERNAME_INVALID');
            }

            // Duplicate Check
            $dup = $db->query()->table(Users::NAME)
                ->where('`username`=?', [$username])
                ->fetch();
            if ($dup->count()) {
                throw new API_Exception('USERNAME_DUP');
            }
        } catch (AppException $e) {
            $e->setParam("username");
            throw $e;
        }

        // Password
        try {
            $password = trim(strval($this->input()->get("password")));
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
        } catch (AppException $e) {
            $e->setParam("password");
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

        // Referrer
        $referrer = trim(strval($this->input()->get("referrer")));
        if ($referrer) {
            try {
                if (!Validator::isValidUsername($referrer)) {
                    throw new API_Exception('REFERRER_ID_INVALID');
                }

                try {
                    $referrerUser = Users::Username($referrer);
                } catch (AppException $e) {
                    if ($e->getCode() === AppException::MODEL_NOT_FOUND) {
                        throw new API_Exception('REFERRER_NOT_FOUND');
                    }

                    throw $e;
                }
            } catch (API_Exception $e) {
                $e->setParam("referrer");
                throw $e;
            }
        }

        // Terms and conditions
        try {
            $terms = trim(strval($this->input()->get("terms")));
            if (!Validator::getBool($terms)) {
                throw new API_Exception('TERMS_UNCHECKED');
            }
        } catch (API_Exception $e) {
            $e->setParam("terms");
            throw $e;
        }

        // Insert User?
        try {
            $db->beginTransaction();

            $apiHmacSecret = Passwords::Generate(16);

            $user = new User();
            $user->id = 0;
            $user->set("checksum", "tba");
            $user->referrer = isset($referrerUser) ? $referrerUser->id : null;
            $user->status = "active";
            $user->firstName = $firstName;
            $user->lastName = $lastName;
            $user->username = $username;
            $user->email = $email;
            $user->isEmailVerified = 0;
            $user->country = $country->code;
            $user->phoneSms = null;
            $user->set("credentials", "tba");
            $user->set("params", "tba");
            $user->joinStamp = time();
            $user->timeStamp = time();

            $user->query()->insert(function () {
                throw new AppControllerException('Failed to insert user row');
            });

            $user->id = Validator::UInt($db->lastInsertId()) ?? 0;
            if (!$user->id) {
                throw new AppControllerException('Invalid user last insert ID');
            }

            // Credentials & Params
            $credentials = new Credentials($user);
            $credentials->hashPassword($password);
            $params = new Params($user);

            $userCipher = $user->cipher();

            $user->set("checksum", $user->checksum()->raw());
            $user->set("credentials", $userCipher->encrypt(clone $credentials)->raw());
            $user->set("params", $userCipher->encrypt(clone $params)->raw());
            $user->set("authToken", $this->apiSession->token()->binary()->raw());
            $user->set("authApiHmac", $apiHmacSecret);

            // Save Changes
            $user->query()->where('id', $user->id)->update(function () {
                throw new AppControllerException('Failed to finalise user row');
            });

            $user->log("signup", null, null, null, ["signup"]);

            // E-mail messages
            UserEmailsPresets::Signup($user, $country);
            UserEmailsPresets::EmailVerifyRequest($user);

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw API_Exception::InternalError();
        }

        // Authenticate API Session
        $this->apiSession->loginAs($user);

        $this->status(true);
        $this->response()->set("username", $user->username);
        $this->response()->set("authHMACSecret", $apiHmacSecret);
    }
}
