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

        try {
            $this->adminPrivileges = $this->adminAcc->privileges();
        } catch (AppException $e) {
            $this->app->errors()->trigger($e->getMessage(), E_USER_WARNING);
            $this->adminPrivileges = new Privileges($this->adminAcc);
        }
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
        $this->page()->title(sprintf('Administrative Account # %d', $this->authAdmin->id))->index(200, 10)
            ->prop("icon", "mdi mdi-shield-edit");

        $this->breadcrumbs("Staff Management", null, "mdi mdi-shield-account");


        $template = $this->template("staff/edit.knit")
            ->assign("privileges", $this->getPrivilegesProps())
            ->assign("isRootAdmin", isset($privileges) && $privileges->root())
            ->assign("adminAcc", $this->adminAcc);
        $this->body($template);
    }
}
