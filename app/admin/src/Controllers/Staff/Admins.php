<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Staff;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Admin\Administrator;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use App\Common\Kernel\ErrorHandler\Errors;
use App\Common\Validator;

/**
 * Class Admins
 * @package App\Admin\Controllers\Staff
 */
class Admins extends AbstractAdminController
{
    /**
     * @throws \App\Common\Exception\AppException
     */
    public function adminCallback(): void
    {
        if (!$this->authAdmin->privileges()->root()) {
            if (!$this->authAdmin->privileges()->viewAdmins) {
                $this->flash()->danger('You do not have privilege to view other administrators');
                $this->redirect($this->authRoot . "dashboard");
                exit;
            }
        }
    }

    /**
     * @throws AppException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Administrators')->index(200, 10)
            ->prop("icon", "mdi mdi-shield-account");

        $this->breadcrumbs("Staff Management", null, "mdi mdi-shield-account");

        try {
            $admins = Administrators::Find()->query("WHERE 1 ORDER BY `id` ASC")->all();
        } catch (\Exception $e) {
            $this->flash()->danger(Errors::Exception2String($e));
            $this->redirect($this->authRoot . "dashboard");
            exit;
        }

        $rows = [];
        $authAdminIsRoot = $this->authAdmin->privileges()->root();

        /** @var Administrator $admin */
        foreach ($admins as $admin) {
            try {
                try {
                    $admin->validate();
                } catch (\Exception $e) {
                }

                try {
                    $privileges = $admin->privileges();
                } catch (AppException $e) {
                    $this->app->errors()->trigger(sprintf('[Admin:%d] %s', $admin->id, $e->getMessage()), E_USER_WARNING);
                }

                $row = Validator::JSON_Filter($admin, sprintf("admin_%d", $admin->id));
                $row["isRoot"] = isset($privileges) ? $privileges->root() : false;
                $row["canEdit"] = true;
                if ($row["isRoot"]) {
                    $row["canEdit"] = false;
                    if ($authAdminIsRoot && $this->authAdmin->id <= $admin->id) {
                        $row["canEdit"] = true;
                    }
                }

                $rows[] = $row;

                unset($row, $privileges);
            } catch (AppException $e) {
                $this->app->errors()->trigger($e->getMessage(), E_USER_WARNING);
            } catch (\Exception $e) {
                $this->app->errors()->trigger($e, E_USER_WARNING);
            }
        }

        $template = $this->template("staff/admins.knit")
            ->assign("admins", $rows);
        $this->body($template);
    }
}
