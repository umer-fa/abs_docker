<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Staff;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Admin\Administrator;
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
        }
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

        try {
            $privileges = $this->adminAcc->privileges();
        } catch (\Exception $e) {
        }

        $template = $this->template("staff/edit.knit")
            ->assign("isRootAdmin", isset($privileges) && $privileges->root())
            ->assign("adminAcc", $this->adminAcc);
        $this->body($template);
    }
}
