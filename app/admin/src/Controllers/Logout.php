<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

/**
 * Class Logout
 * @package App\Admin\Controllers
 */
class Logout extends AbstractAdminController
{
    public function adminCallback(): void
    {
    }

    /**
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function get(): void
    {
        $this->authAdmin->log('Logout', get_called_class(), null, ["auth"]);
        $this->session()->bags()->bag("App")->delete("Administration");
        $this->flash()->info('You are now logged out!');
        $this->redirect($this->request()->url()->root() . "login");
        exit;
    }
}
