<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Staff;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Admin\Administrator;
use App\Common\Admin\Credentials;
use App\Common\Admin\Privileges;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Kernel\ErrorHandler\Errors;
use App\Common\Validator;
use Comely\Utils\Security\Passwords;

/**
 * Class Edit
 * @package App\Admin\Controllers\Staff
 */
class Edit extends AbstractAdminController
{
    /** @var Administrator */
    private Administrator $adminAcc;
    /** @var Privileges */
    private Privileges $adminPrivileges;

    /**
     * @return void
     */
    public function adminCallback(): void
    {
        try {
            $queryAdminId = explode("=", strval($this->request()->url()->query()))[0];
            $adminId = Validator::UInt($this->input()->get("adminId") ?? $queryAdminId ?? null);
            if (!$adminId) {
                throw new AppControllerException('No administrator selected');
            }

            $this->adminAcc = Administrators::get($adminId);

            try {
                $this->adminPrivileges = $this->adminAcc->privileges();
            } catch (AppException $e) {
                $this->app->errors()->trigger($e->getMessage(), E_USER_WARNING);
                $this->adminPrivileges = new Privileges($this->adminAcc);
            }

            if (!$this->authAdmin->privileges()->root()) {
                throw new AppControllerException('Only root admins can edit/reset administrative accounts');
            } else {
                if ($this->adminAcc->privileges()->root()) {
                    if ($this->adminAcc->id < $this->authAdmin->id) {
                        throw new AppControllerException('You cannot edit this root administrative account');
                    }
                }
            }
        } catch (\Exception $e) {
            $this->flash()->danger($e instanceof AppException ? $e->getMessage() : Errors::Exception2String($e));
            $this->redirect($this->authRoot . "staff/admins");
            exit;
        }

        try {
            $this->adminAcc->validate();
        } catch (AppException $e) {
            $this->app->errors()->trigger($e->getMessage(), E_USER_WARNING);
        }
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postReset(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck(30);

        $action = strtolower(trim(strval($this->input()->get("action"))));
        switch ($action) {
            case "checksum":
                $this->reComputeChecksum();
                break;
            case "disable2fa":
                $this->disable2FA();
                break;
            case "credentials":
                $this->resetCredentials();
                break;
            case "privileges":
                $this->resetPrivileges();
                break;
            default:
                throw new AppControllerException('Unknown action to perform');
        }
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function resetPrivileges(): void
    {
        try {
            $adminPrivileges = $this->adminAcc->privileges();
        } catch (AppException $e) {
        }

        if (isset($adminPrivileges)) {
            throw new AppControllerException('Privileges object for this admin account is OK');
        }

        $db = $this->app->db()->primary();
        try {
            $adminCipher = $this->adminAcc->cipher();
            $this->adminAcc->set("privileges", $adminCipher->encrypt(new Privileges($this->adminAcc))->raw());
            $this->adminAcc->query()->update(function () {
                throw new AppControllerException('Failed to update administrative account');
            });

            $this->authAdmin->log(
                sprintf('Admin [#%d] privileges reset', $this->adminAcc->id),
                __CLASS__,
                null,
                [sprintf("admins:%d", $this->authAdmin->id)]
            );
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to update administrator row');
        }

        $this->response()->set("status", true);
        $this->messages()->success("Privileges object has been reset for this administrator");
        $this->flash()->success("Privileges object has been reset for this administrator");
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
            $adminAccCredentials = $this->adminAcc->credentials();
        } catch (AppException $e) {
        }

        if (isset($adminAccCredentials)) {
            throw new AppControllerException('Credentials for this admin account is OK');
        }

        $db = $this->app->db()->primary();
        try {
            $adminCipher = $this->adminAcc->cipher();
            $this->adminAcc->set("credentials", $adminCipher->encrypt(new Credentials($this->adminAcc))->raw());
            $this->adminAcc->query()->update(function () {
                throw new AppControllerException('Failed to update administrative account');
            });

            $this->authAdmin->log(
                sprintf('Admin [#%d] credentials reset', $this->adminAcc->id),
                __CLASS__,
                null,
                [sprintf("admins:%d", $this->authAdmin->id)]
            );
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to update administrator row');
        }

        $this->response()->set("status", true);
        $this->messages()->success("Credentials have been reset for this administrator");
        $this->flash()->success("Credentials have been reset for this administrator");
        $this->response()->set("refresh", true);
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function disable2FA(): void
    {
        try {
            $adminAccCredentials = $this->adminAcc->credentials();
        } catch (AppException $e) {
            throw new AppControllerException('Cannot disable 2FA, credentials object is corrupted');
        }

        if (!$adminAccCredentials->getGoogleAuthSeed()) {
            throw new AppControllerException('Google 2FA is already disabled on this account');
        }

        $db = $this->app->db()->primary();

        try {
            $adminCipher = $this->adminAcc->cipher();
            $adminAccCredentials->setGoogleAuthSeed(null); // set to NULL

            $this->adminAcc->set("credentials", $adminCipher->encrypt(clone $adminAccCredentials)->raw());
            $this->adminAcc->query()->update(function () {
                throw new AppControllerException('Failed to update administrative account');
            });

            $this->authAdmin->log(
                sprintf('Admin [#%d] 2FA disabled', $this->adminAcc->id),
                __CLASS__,
                null,
                [sprintf("admins:%d", $this->authAdmin->id)]
            );
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to update administrator row');
        }

        $this->response()->set("status", true);
        $this->messages()->success("2FA has been disabled for this administrator");
        $this->flash()->success("2FA has been disabled for this administrator");
        $this->response()->set("refresh", true);
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function reComputeChecksum(): void
    {
        if ($this->adminAcc->_checksumVerified) {
            throw new AppControllerException('Checksum does not require recompute');
        }

        $db = $this->app->db()->primary();

        try {
            $this->adminAcc->set("checksum", $this->adminAcc->checksum()->raw());
            $this->adminAcc->query()->update(function () {
                throw new AppControllerException('Failed to update administrative account');
            });

            $this->authAdmin->log(
                sprintf('Admin [#%d] checksum recomputed', $this->adminAcc->id),
                __CLASS__,
                null,
                [sprintf("admins:%d", $this->authAdmin->id)]
            );
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to update administrator row');
        }

        $this->response()->set("status", true);
        $this->messages()->success("Administrative account checksum updated");
        $this->flash()->success("Administrative account checksum updated");
        $this->response()->set("refresh", true);
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     * @throws \Comely\Utils\Security\Exception\SecurityUtilException
     */
    public function postEdit(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck(30);

        // Validate
        if (!$this->adminAcc->_checksumVerified) {
            throw new AppControllerException('Administrative account checksum is invalid');
        } elseif ($this->authAdmin->id === $this->adminAcc->id) {
            throw new AppControllerException('You cannot edit your own account');
        }

        // Changes?
        $adminNewEmail = null;
        $adminNewPassword = null;
        $adminNewStatus = null;

        $db = $this->app->db()->primary();

        // Status
        $currentStatus = $this->adminAcc->status === 1;
        $newStatus = Validator::getBool(trim(strval($this->input()->get("status"))));
        if ($newStatus !== $currentStatus) {
            $adminNewStatus = $newStatus;
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

            if ($email !== $this->adminAcc->email) {
                $adminNewEmail = $email;
                $dup = $db->query()->table(Administrators::NAME)
                    ->where('`email`=?', [$adminNewEmail])
                    ->fetch();
                if ($dup->count()) {
                    throw new AppControllerException('E-mail address is already in use!');
                }
            }
        } catch (AppControllerException $e) {
            $e->setParam("email");
            throw $e;
        }

        // Password
        $adminNewPassword = trim(strval($this->input()->get("adminNewPass")));
        if (!$adminNewPassword) {
            $adminNewPassword = null;
        } else {
            try {
                try {
                    $adminAccCredentials = $this->adminAcc->credentials();
                } catch (AppException $e) {
                    throw new AppControllerException('Credentials object is corrupted');
                }

                $newPasswordLen = strlen($adminNewPassword);
                if ($newPasswordLen <= 5) {
                    throw new AppControllerException('New password is too short');
                } elseif ($newPasswordLen > 32) {
                    throw new AppControllerException('New password is too long');
                } elseif (Passwords::Strength($adminNewPassword) < 4) {
                    throw new AppControllerException('New password is too weak!');
                }

                if ($adminAccCredentials->verifyPassword($adminNewPassword)) {
                    throw new AppControllerException('New password cannot be same as existing one!');
                }
            } catch (AppException $e) {
                $e->setParam("adminNewPass");
                throw $e;
            }
        }

        // Save changes?
        if (!is_bool($adminNewStatus) && !$adminNewEmail && !$adminNewPassword) {
            throw new AppControllerException('There are no changes to be saved!');
        }

        if ($adminNewEmail) {
            $oldEmail = $this->adminAcc->email;
            $this->adminAcc->email = $adminNewEmail;
            $newEmailLog = sprintf('Admin [#%d] email changed from "%s" to "%s"', $this->adminAcc->id, $oldEmail, $adminNewEmail);
        }

        if (is_bool($adminNewStatus)) {
            $this->adminAcc->status = $adminNewStatus ? 1 : 0;
            $newStatusLog = sprintf(
                'Admin [#%d] status changed to %s',
                $this->adminAcc->id,
                $adminNewStatus ? "ENABLED" : "DISABLED"
            );
        }

        if ($adminNewPassword && isset($adminAccCredentials)) {
            $adminCipher = $this->adminAcc->cipher();
            $adminAccCredentials->setPassword($adminNewPassword);
            $this->adminAcc->set("credentials", $adminCipher->encrypt(clone $adminAccCredentials)->raw());
            $newPasswordLog = sprintf('Admin [#%d] %s password reset', $this->adminAcc->id, $this->adminAcc->email);
        }

        try {
            $db->beginTransaction();

            $this->adminAcc->set("checksum", $this->adminAcc->checksum()->raw());

            $this->adminAcc->query()->update(function () {
                throw new AppControllerException('Failed to update administrative account');
            });

            $adminsFlag = sprintf("admins:%d", $this->authAdmin->id);

            // Create Logs
            if (isset($newEmailLog)) {
                $this->authAdmin->log($newEmailLog, __CLASS__, null, [$adminsFlag]);
            }

            if (isset($newStatusLog)) {
                $this->authAdmin->log($newStatusLog, __CLASS__, null, [$adminsFlag]);
            }

            if (isset($newPasswordLog)) {
                $this->authAdmin->log($newPasswordLog, __CLASS__, null, [$adminsFlag]);
            }

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to insert administrator row');
        }

        $this->response()->set("status", true);
        $this->messages()->success("Administrative account updated");

        if (isset($newEmailLog)) {
            $this->messages()->info($newEmailLog);
        }

        if (isset($newStatusLog)) {
            $this->messages()->info($newStatusLog);
        }

        if (isset($newPasswordLog)) {
            $this->messages()->info($newPasswordLog);
        }
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postPrivileges(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck(30);

        // Make sure checksum and privileges object is valid
        if (!$this->adminAcc->_checksumVerified) {
            throw new AppControllerException('Administrative account checksum is invalid');
        } elseif ($this->adminPrivileges->root()) {
            throw new AppControllerException('Root administrative accounts do not need privileges');
        }

        $db = $this->app->db()->primary();
        $changes = 0;

        // Privileges
        $privileges = $this->getPrivilegesProps();
        foreach ($privileges as $privilege) {
            $privilegeProp = $privilege["prop"];
            $privilegeCurrent = $privilege["current"];
            $newTrigger = Validator::getBool(trim(strval($this->input()->get($privilegeProp))));
            if ($privilegeCurrent !== $newTrigger) {
                $this->adminPrivileges->$privilegeProp = $newTrigger;
                $changes++;
            }

            unset($privilegeProp, $privilegeCurrent, $newTrigger);
        }

        // Changes?
        if (!$changes) {
            throw new AppControllerException('There are no changes to be saved');
        }

        try {
            $db->beginTransaction();

            $adminCipher = $this->adminAcc->cipher();
            $this->adminAcc->set("privileges", $adminCipher->encrypt(clone $this->adminPrivileges)->raw());
            $this->adminAcc->query()->update(function () {
                throw new AppControllerException('Failed to update privileges column');
            });

            // Create Log
            $this->authAdmin->log(
                sprintf('Administrator [#%d] "%s" privileges updated', $this->adminAcc->id, $this->adminAcc->email),
                __CLASS__,
                __LINE__,
                [sprintf("admins:%d", $this->authAdmin->id)]
            );

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to insert administrator row');
        }

        $this->response()->set("status", true);
        $this->messages()->success("Administrative privileges updated");
    }

    /**
     * @return array
     */
    private function getPrivilegesProps(): array
    {
        $props = [];
        $props[] = [
            "prop" => "viewAdmins",
            "label" => "View all Administrative accounts",
            "current" => $this->adminPrivileges->viewAdmins,
        ];

        $props[] = [
            "prop" => "viewAdminsLogs",
            "label" => "View logs of other Administrators",
            "current" => $this->adminPrivileges->viewAdminsLogs,
        ];

        $props[] = [
            "prop" => "viewConfig",
            "label" => "View Configurations",
            "current" => $this->adminPrivileges->viewConfig,
        ];

        $props[] = [
            "prop" => "editConfig",
            "label" => "Edit Configurations",
            "current" => $this->adminPrivileges->editConfig,
        ];

        $props[] = [
            "prop" => "viewUsers",
            "label" => "Search/Browse Users",
            "current" => $this->adminPrivileges->viewUsers,
        ];

        $props[] = [
            "prop" => "manageUsers",
            "label" => "Add/Edit Users",
            "current" => $this->adminPrivileges->manageUsers,
        ];

        $props[] = [
            "prop" => "viewAPIQueriesPayload",
            "label" => "View API queries payloads",
            "current" => $this->adminPrivileges->viewAPIQueriesPayload,
        ];

        return $props;
    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title("Edit Admin")->index(200, 10)
            ->prop("icon", "mdi mdi-shield-edit");

        $this->breadcrumbs("Staff Management", null, "mdi mdi-shield-account");

        $template = $this->template("staff/edit.knit")
            ->assign("privileges", $this->getPrivilegesProps())
            ->assign("isRootAdmin", $this->adminPrivileges->root())
            ->assign("adminAcc", $this->adminAcc);
        $this->body($template);
    }
}
