<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Staff;

use App\Admin\Controllers\AbstractAdminController;

/**
 * Class CreateAdmin
 * @package App\Admin\Controllers\Staff
 */
class CreateAdmin extends AbstractAdminController
{
    /**
     * @throws \App\Common\Exception\AppException
     */
    public function adminCallback(): void
    {
        if (!$this->authAdmin->privileges()->root()) {
            $this->flash()->danger('Only root administrators can create new admin accounts');
            $this->redirect($this->authRoot . "dashboard");
            exit;
        }
    }

    public function post(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck();


    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Create Administrator')->index(200, 10)
            ->prop("icon", "mdi mdi-shield-account");

        $this->breadcrumbs("Staff Management", null, "mdi mdi-shield-account");

        $template = $this->template("staff/create_admin.knit");
        $this->body($template);
    }
}
