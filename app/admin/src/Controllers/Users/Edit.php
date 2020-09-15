<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Users;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Database\Primary\Administrators\Logs;
use App\Common\Database\Primary\Countries;
use App\Common\Database\Primary\Users;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Kernel\ErrorHandler\Errors;
use App\Common\Kernel\KnitModifiers;
use App\Common\Users\Credentials;
use App\Common\Users\Params;
use App\Common\Users\User;
use App\Common\Validator;
use Comely\Database\Exception\DatabaseException;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;
use Comely\Utils\Security\Passwords;

/**
 * Class Edit
 * @package App\Admin\Controllers\Users
 */
class Edit extends AbstractAdminController
{
    /** @var User */
    private User $user;
    /** @var User|null */
    private ?User $referrer = null;

    /**
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function adminCallback(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Countries');
        Schema::Bind($db, 'App\Common\Database\Primary\Users');

        try {
            if (!$this->authAdmin->privileges()->root()) {
                if (!$this->authAdmin->privileges()->viewUsers) {
                    throw new AppControllerException('You do not have privilege to view users');
                }
            }

            $queryUserId = explode("=", strval($this->request()->url()->query()))[0];
            $userId = Validator::UInt($this->input()->get("userId") ?? $queryUserId ?? null);
            if (!$userId) {
                throw new AppControllerException('No user selected');
            }

            $this->user = Users::get($userId);
        } catch (\Exception $e) {
            $this->flash()->danger($e instanceof AppException ? $e->getMessage() : Errors::Exception2String($e));
            $this->redirect($this->authRoot . "users/search");
            exit;
        }

        try {
            $this->user->validate();
        } catch (AppException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        // Referrer
        if ($this->user->referrer) {
            try {
                $referrer = Users::get($this->user->referrer);
                $this->referrer = $referrer;
            } catch (AppException $e) {
                trigger_error(sprintf('Failed to retrieve Referrer id # %d', $this->user->referrer), E_USER_WARNING);
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        }
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postEdit(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck();
        $this->editUserPrivilegeCheck();
        $db = $this->app->db()->primary();

        // Checksum OK?
        if (!$this->user->_checksumVerified) {
            throw new AppControllerException('Cannot update; User checksum must be recomputed first');
        }

        // Referrer
        $referrer = trim(strval($this->input()->get("referrer")));
        if ($referrer) {
            try {
                $referrer = Users::Username($referrer);
                if ($this->user->referrer !== $referrer->id) {
                    try {
                        $referrer->validate();
                    } catch (AppException $e) {
                        throw new AppControllerException('Referrer checksum validation fail');
                    }
                }

                if ($referrer->id === $this->user->id) {
                    throw new AppControllerException('User cannot be its own referrer');
                }
            } catch (AppException $e) {
                $e->setParam("referrer");
                throw $e;
            }
        }

        $referrerChange = false;
        if (!$this->user->referrer && $referrer) {
            $referrerChange = true;
        } elseif ($this->user->referrer && !$referrer) {
            $referrerChange = true;
        } elseif ($this->user->referrer && $referrer) {
            if ($this->user->referrer !== $referrer->id) {
                $referrerChange = true;
            }
        }

        if ($referrerChange) {
            $this->user->referrer = $referrer ? $referrer->id : 0;
            $referrerChangeLog = sprintf(
                'User "%s" referrer changed from %s to %s',
                $this->user->username,
                $this->referrer ? sprintf('"%s"', $this->referrer->username) : "NULL",
                $referrer ? sprintf('"%s"', $referrer->username) : "NULL"
            );
        }

        // Status
        $status = trim(strval($this->input()->get("status")));
        try {
            if (!in_array($status, ["active", "frozen", "disabled"])) {
                throw new AppControllerException('Invalid user status');
            }

            if ($status !== $this->user->status) {
                $statusChangeLog = sprintf('User "%s" status changed from %s to %s', $this->user->username, strtoupper($this->user->status), strtoupper($status));
                $this->user->status = $status;
            }
        } catch (AppException $e) {
            $e->setParam("status");
            throw $e;
        }

        // First name
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

        $this->user->firstName = $firstName;

        // Last name
        try {
            $lastName = trim(strval($this->input()->get("last_name")));
            $lastNameLen = strlen($lastName);
            if (!$lastName) {
                throw new AppControllerException('Last name is required');
            } elseif ($lastNameLen < 2) {
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

        $this->user->lastName = $lastName;

        // E-mail Address
        $email = trim(strval($this->input()->get("email")));
        try {
            if (!$email) {
                throw new AppControllerException('E-mail address is required');
            } elseif (!Validator::isValidEmailAddress($email)) {
                throw new AppControllerException('Invalid e-mail address');
            } elseif (strlen($email) > 64) {
                throw new AppControllerException('E-mail address is too long');
            }

            if ($email !== $this->user->email) {
                // Changing Email address...
                $dupEm = $db->query()->table(Users::NAME)
                    ->where('email=?', [$email])
                    ->fetch();
                if ($dupEm->count()) {
                    throw new AppControllerException('E-mail address is already in use!');
                }

                $emailChangeLog = sprintf('User "%s" e-mail changed from "%s" to "%s"', $this->user->username, $this->user->email, $email);
                $this->user->email = $email;
            }
        } catch (AppException $e) {
            $e->setParam("email");
            throw $e;
        }

        $emailIsVerified = Validator::getBool(trim(strval($this->input()->get("isEmailVerified"))));
        $currentEmStatus = $this->user->isEmailVerified === 1;
        if ($currentEmStatus !== $emailIsVerified) {
            $this->user->isEmailVerified = $emailIsVerified ? 1 : 0;
            $emailVerifyLog = sprintf(
                'User "%s" e-mail verification changed to %s',
                $this->user->username,
                $emailIsVerified ? "VERIFIED" : "NOT VERIFIED"
            );
        }

        // Save Changes?
        if (!$this->user->changes()) {
            throw new AppControllerException('There are no changes to be saved!');
        }

        try {
            $db->beginTransaction();

            $this->user->set("checksum", $this->user->checksum()->raw());
            $this->user->query()->update();

            $usersFlag = sprintf('users:%d', $this->user->id);

            if (isset($referrerChangeLog)) {
                $this->authAdmin->log($referrerChangeLog, __CLASS__, null, [$usersFlag]);
            }

            if (isset($statusChangeLog)) {
                $this->authAdmin->log($statusChangeLog, __CLASS__, null, [$usersFlag]);
            }

            if (isset($emailChangeLog)) {
                $this->authAdmin->log($emailChangeLog, __CLASS__, null, [$usersFlag]);
            }

            if (isset($emailVerifyLog)) {
                $this->authAdmin->log($emailVerifyLog, __CLASS__, null, [$usersFlag]);
            }

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to update user');
        }

        $this->user->deleteCached();

        if (isset($referrerChangeLog)) {
            $this->messages()->info($referrerChangeLog);
        }

        if (isset($statusChangeLog)) {
            $this->messages()->info($statusChangeLog);
        }

        if (isset($emailChangeLog)) {
            $this->messages()->info($emailChangeLog);
        }

        if (isset($emailVerifyLog)) {
            $this->messages()->info($emailVerifyLog);
        }

        $this->response()->set("status", true);
        $this->messages()->success("User account has been updated!");
        $this->response()->set("refresh", true);
        $this->response()->set("disabled", true);
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postPassword(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck();
        $this->editUserPrivilegeCheck();

        // Credentials
        try {
            $credentials = $this->user->credentials();
        } catch (\Exception $e) {
            $this->app->errors()->trigger($e instanceof AppException ? $e->getMessage() : $e, E_USER_WARNING);
            throw new AppControllerException('Cannot retrieve user credentials');
        }

        // New Password
        $newPassword = trim(strval($this->input()->get("newPassword")));
        try {
            $newPasswordLen = strlen($newPassword);
            if (!$newPassword) {
                throw new AppControllerException('New password is required');
            } elseif ($newPasswordLen < 6) {
                throw new AppControllerException('New Password is too short');
            } elseif ($newPasswordLen > 32) {
                throw new AppControllerException('New Password is too long');
            } elseif (Passwords::Strength($newPassword) < 4) {
                throw new AppControllerException('New Password is too weak!');
            }

            if ($credentials->verifyPassword($newPassword)) {
                throw new AppControllerException('New password must be different from existing one!');
            }
        } catch (AppException $e) {
            $e->setParam("newPassword");
            throw $e;
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $userCipher = $this->user->cipher();
            $credentials->hashPassword($newPassword);
            $this->user->set("credentials", $userCipher->encrypt(clone $credentials)->raw());
            $this->user->query()->update();

            $this->authAdmin->log(
                sprintf('User "%s" password changed', $this->user->username),
                __CLASS__,
                null,
                [sprintf('users:%d', $this->user->id)]
            );

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to update user password');
        }

        $this->user->deleteCached();

        $this->response()->set("status", true);
        $this->messages()->success("User password has been updated successfully!");
        $this->response()->set("refresh", true);
        $this->response()->set("disabled", true);
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws DatabaseException
     * @throws \App\Common\Exception\XSRF_Exception
     */
    public function postReset(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck();
        $this->editUserPrivilegeCheck();

        $action = strtolower(trim(strval($this->input()->get("action"))));
        switch ($action) {
            case "checksum":
                $this->recomputeChecksum();
                return;
            case "disabled2fa":
                $this->disable2FA();
                return;
            case "credentials":
                $this->resetCredentials();
                return;
            case "params":
                $this->resetParams();
                return;
            default:
                throw new AppControllerException('Invalid reset action to perform!');
        }
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function disable2FA(): void
    {
        try {
            $credentials = $this->user->credentials();
        } catch (\Exception $e) {
        }

        if (!isset($credentials)) {
            throw new AppControllerException('Cannot disable 2FA; User credentials must be RESET');
        } elseif (!$credentials->googleAuthSeed) {
            throw new AppControllerException('2FA is already disabled for this user!');
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $userCipher = $this->user->cipher();
            $credentials->googleAuthSeed = null;
            $this->user->set("credentials", $userCipher->encrypt(clone $credentials)->raw());
            $this->user->query()->update();
            $this->authAdmin->log(
                sprintf('User "%s" disabled 2FA', $this->user->username),
                __CLASS__,
                null,
                [sprintf('users:%d', $this->user->id)]
            );

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to update user');
        }

        $this->user->deleteCached();

        $this->flash()->danger("2FA has been disabled for this user!");

        $this->response()->set("status", true);
        $this->messages()->success("2FA has been disabled for this user!");
        $this->response()->set("disabled", true);
        $this->response()->set("refresh", true);
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function resetParams(): void
    {
        try {
            $params = $this->user->params();
        } catch (\Exception $e) {
        }

        if (isset($params)) {
            throw new AppControllerException('User params object is OK');
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $userCipher = $this->user->cipher();
            $newParams = new Params($this->user);
            $this->user->set("params", $userCipher->encrypt($newParams)->raw());
            $this->user->query()->update();
            $this->authAdmin->log(
                sprintf('User "%s" params obj RESET', $this->user->username),
                __CLASS__,
                null,
                [sprintf('users:%d', $this->user->id)]
            );

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to update user');
        }

        $this->user->deleteCached();

        $this->flash()->info("User params object has been reset!");

        $this->response()->set("status", true);
        $this->messages()->success("User params object has been reset!");
        $this->response()->set("disabled", true);
        $this->response()->set("refresh", true);
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function resetCredentials(): void
    {
        try {
            $credentials = $this->user->credentials();
        } catch (\Exception $e) {
        }

        if (isset($credentials)) {
            throw new AppControllerException('User credentials object is OK');
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $userCipher = $this->user->cipher();
            $newCredentials = new Credentials($this->user);
            $this->user->set("credentials", $userCipher->encrypt($newCredentials)->raw());
            $this->user->query()->update();
            $this->authAdmin->log(
                sprintf('User "%s" credentials RESET', $this->user->username),
                __CLASS__,
                null,
                [sprintf('users:%d', $this->user->id)]
            );

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to update user');
        }

        $this->user->deleteCached();

        $this->flash()->info("User credentials object has been reset!");
        $this->flash()->info("User's password reset is required!");

        $this->response()->set("status", true);
        $this->messages()->success("User credentials object has been reset!");
        $this->response()->set("disabled", true);
        $this->response()->set("refresh", true);
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function recomputeChecksum(): void
    {
        if ($this->user->_checksumVerified) {
            throw new AppControllerException('User checksum does not require recompute');
        }

        $db = $this->app->db()->primary();

        try {
            $db->beginTransaction();

            $this->user->set("checksum", $this->user->checksum()->raw());
            $this->user->query()->update();
            $this->authAdmin->log(
                sprintf('User "%s" checksum RESET', $this->user->username),
                __CLASS__,
                null,
                [sprintf('users:%d', $this->user->id)]
            );

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to update user');
        }

        $this->user->deleteCached();

        $this->flash()->info("User checksum has been recomputed!");

        $this->response()->set("status", true);
        $this->messages()->success("User checksum has been recomputed!");
        $this->response()->set("disabled", true);
        $this->response()->set("refresh", true);
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     */
    private function editUserPrivilegeCheck(): void
    {
        if (!$this->authAdmin->privileges()->root()) {
            if (!$this->authAdmin->privileges()->manageUsers) {
                throw new AppControllerException('You do not have privilege to edit users');
            }
        }
    }

    /**
     * @throws AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title($this->user->username)->index(1100, 10)
            ->prop("icon", "mdi mdi-account-edit-outline");

        $this->breadcrumbs("Users Control", null, "ion ion-ios-people");

        // Country
        try {
            $country = Countries::get($this->user->country);
        } catch (AppException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        // Administrative History
        try {
            $adminLogs = Logs::Find()
                ->query("WHERE `flags` LIKE ? ORDER BY `id` DESC", [sprintf('%%users:%d%%', $this->user->id)])
                ->all();
        } catch (ORM_ModelNotFoundException $e) {
        } catch (\Exception $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
        }

        // Last Seen Log
        $db = $this->app->db()->primary();
        $lastSeenOn = null;
        try {
            $lastSeenLog = $db->query()->table(Users\Logs::NAME)
                ->cols("time_stamp")
                ->where('`user`=?', [$this->user->id])
                ->desc("time_stamp")
                ->limit(1)
                ->fetch()
                ->first();
            if ($lastSeenLog) {
                $lastSeenOn = $lastSeenLog["time_stamp"];
            }
        } catch (DatabaseException $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
        }

        // Knit Modifiers
        KnitModifiers::Dated($this->knit());
        KnitModifiers::Null($this->knit());

        $template = $this->template("users/edit.knit")
            ->assign("user", $this->user)
            ->assign("referrer", $this->referrer)
            ->assign("adminLogs", isset($adminLogs) ? $adminLogs : [])
            ->assign("country", isset($country) ? $country : [])
            ->assign("lastSeenOn", $lastSeenOn)
            ->assign("countries", Countries::List());
        $this->body($template);
    }
}
