<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Staff;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Admin\Administrator;
use App\Common\Admin\Privileges;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Validator;

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
                    if ($this->adminAcc->id > $this->authAdmin->id) {
                        throw new AppControllerException('You cannot edit this root administrative account');
                    }
                }
            }
        } catch (\Exception $e) {
            if ($e instanceof AppException) {
                $this->flash()->danger($e->getMessage());
            } else {
                $this->app->errors()->trigger($e, E_USER_WARNING);
            }

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
                ["admins", $this->adminAcc->id]
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
        $this->messages()->info("Redirecting...");
        $this->response()->set("disabled", true);
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
