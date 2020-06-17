<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Staff;

use App\Admin\Controllers\AbstractAdminController;
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
            $admins = Administrators::Find()->asc("id")->all();
        } catch (\Exception $e) {
            $this->flash()->danger(Errors::Exception2String($e));
            $this->redirect($this->authRoot . "dashboard");
            exit;
        }

        var_dump($admins);

        $template = $this->template("staff/admins.knit");
        $this->body($template);
    }
}
