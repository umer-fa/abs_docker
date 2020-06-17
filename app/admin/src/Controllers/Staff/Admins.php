<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Staff;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Admin\Administrator;
use App\Common\Database\Primary\Administrators;
use App\Common\Kernel\ErrorHandler\Errors;

/**
 * Class Admins
 * @package App\Admin\Controllers\Staff
 */
class Admins extends AbstractAdminController
{
    public function adminCallback(): void
    {
    }

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

        $adminsCount = count($admins);
        for ($i = 0; $i < $adminsCount; $i++) {
            /** @var Administrator $admin */
            $admin = $admins[$i];
            try {
                $admin->validate();
            } catch (\Exception $e) {
            }
        }

        $template = $this->template("staff/admins.knit")
            ->assign("admins", $admins);
        $this->body($template);
    }
}
