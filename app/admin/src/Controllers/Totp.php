<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

/**
 * Class Totp
 * @package App\Admin\Controllers
 */
class Totp extends AbstractAdminController
{
    public function adminCallback(): void
    {
    }

    /**
     * @throws \App\Common\Exception\AppControllerException
     * @throws \App\Common\Exception\AppException
     * @throws \App\Common\Exception\XSRF_Exception
     */
    public function post(): void
    {
        $this->verifyXSRF();
        $this->verifyTotp($this->input()->get("totp"));

        $totpBag = $this->session()->bags()->bag("App")->bag("Administration")->bag("totp");
        $totpBag->set("lastCheckedOn", time());

        $this->response()->set("status", true);
        $this->response()->set("totpModalClose", true);
        $this->messages()->success('TOTP authenticated; Please continue...');
    }
}
