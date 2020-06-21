<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Users;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Database\Primary\Countries;
use App\Common\Database\Primary\Users;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Users\Credentials;
use App\Common\Users\Params;
use App\Common\Users\User;
use App\Common\Validator;
use Comely\Database\Schema;
use Comely\Utils\Security\Passwords;

/**
 * Class Add
 * @package App\Admin\Controllers\Users
 */
class Add extends AbstractAdminController
{
    /**
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function adminCallback(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Countries');
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
    }

    /**
     * @throws AppControllerException
     * @throws \App\Common\Exception\AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck();

        if (!$this->authAdmin->privileges()->root()) {
            if (!$this->authAdmin->privileges()->manageUsers) {
                throw new AppControllerException('You do not have permission to add new user');
            }
        }

        $db = $this->app->db()->primary();

        try {
            $firstName = trim(strval($this->input()->get("first_name")));
            $firstNameLen = strlen($firstName);
            if (!$firstName) {
                throw new AppControllerException('First name is required');
            } elseif ($firstNameLen < 3) {
                throw new AppControllerException('First name is too short');
            } elseif ($firstNameLen > 32) {
                throw new AppControllerException('First name is too long');
            } elseif (!preg_match('/^[a-z]+(\s[a-z]+)*$/i', $firstName)) {
                throw new AppControllerException('First name contains an illegal character');
            }
        } catch (AppControllerException $e) {
            $e->setParam("first_name");
            throw $e;
        }

        try {
            $lastName = trim(strval($this->input()->get("last_name")));
            $lastNameLen = strlen($lastName);
            if (!$lastName) {
                throw new AppControllerException('Last name is required');
            } elseif ($lastNameLen < 3) {
                throw new AppControllerException('Last name is too short');
            } elseif ($lastNameLen > 32) {
                throw new AppControllerException('Last name is too long');
            } elseif (!preg_match('/^[a-z]+(\s[a-z]+)*$/i', $lastName)) {
                throw new AppControllerException('Last name contains an illegal character');
            }
        } catch (AppControllerException $e) {
            $e->setParam("last_name");
            throw $e;
        }

        try {
            $country = trim(strval($this->input()->get("country")));
            if (!$country) {
                throw new AppControllerException('Country is required');
            }

            $country = Countries::get($country);
        } catch (AppException $e) {
            $e->setParam("country");
            throw $e;
        }

        // E-mail address
        try {
            $email = trim(strval($this->input()->get("email")));
            if (!$email) {
                throw new AppControllerException('E-mail address is required');
            } elseif (strlen($email) > 64) {
                throw new AppControllerException('E-mail address is too long');
            } elseif (!Validator::isValidEmailAddress($email)) {
                throw new AppControllerException('Invalid e-mail address');
            }

            // Duplicate Check
            $dup = $db->query()->table(Users::NAME)
                ->where('`email`=?', [$email])
                ->fetch();
            if ($dup->count()) {
                throw new AppControllerException('E-mail address is already registered!');
            }
        } catch (AppControllerException $e) {
            $e->setParam("email");
            throw $e;
        }

        // Username
        try {
            $username = trim(strval($this->input()->get("username")));
            $usernameLen = strlen($username);
            if ($usernameLen <= 4) {
                throw new AppControllerException('Username is too short');
            } elseif ($usernameLen >= 20) {
                throw new AppControllerException('Username is too long');
            } elseif (!Validator::isValidUsername($username)) {
                throw new AppControllerException('Username contains an illegal character');
            }

            // Duplicate Check
            $dup = $db->query()->table(Users::NAME)
                ->where('`username`=?', [$username])
                ->fetch();
            if ($dup->count()) {
                throw new AppControllerException('Username is already registered!');
            }
        } catch (AppControllerException $e) {
            $e->setParam("username");
            throw $e;
        }

        // Password
        try {
            $password = trim(strval($this->input()->get("password")));
            $passwordLen = strlen($password);
            if (!$password) {
                throw new AppControllerException('Password is required');
            } elseif ($passwordLen <= 5) {
                throw new AppControllerException('Password is too short');
            } elseif ($passwordLen > 32) {
                throw new AppControllerException('Password is too long');
            } elseif (Passwords::Strength($password) < 4) {
                throw new AppControllerException('Password is too weak!');
            }
        } catch (AppControllerException $e) {
            $e->setParam("password");
            throw $e;
        }

        // Create User Account
        try {
            $db->beginTransaction();

            $user = new User();
            $user->id = 0;
            $user->set("checksum", "tba");
            $user->referrer = null;
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

            // Save Changes
            $user->query()->where('id', $user->id)->update(function () {
                throw new AppControllerException('Failed to finalise user row');
            });

            // Create Log
            $this->authAdmin->log(
                sprintf('User [#%d] "%s" created', $user->id, $user->username),
                __CLASS__,
                __LINE__,
                [sprintf('users:%d', $user->id)]
            );

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppException('Failed to insert user row');
        }

        $this->response()->set("status", true);
        $this->messages()->success("New user account has been registered!");
        $this->messages()->info("Redirecting...");
        $this->response()->set("disabled", true);
        $this->response()->set("redirect", $this->authRoot . "users/search?key=email&value=" . $user->email);

    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Create User')->index(1100, 20)
            ->prop("icon", "mdi mdi-account-plus-outline");

        $this->breadcrumbs("Users Control", null, "ion ion-ios-people");

        $template = $this->template("users/add.knit")
            ->assign("countries", Countries::List());
        $this->body($template);
    }
}
