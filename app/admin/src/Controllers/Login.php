<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

use App\Common\Exception\AppControllerException;

/**
 * Class Login
 * @package App\Admin\Controllers
 */
class Login extends AbstractAdminController
{
    public function adminCallback(): void
    {
    }

    /**
     * @throws \App\Common\Exception\ObfuscatedFormsException
     * @throws \App\Common\Exception\XSRF_Exception
     */
    public function post(): void
    {
        $form = $this->getObfuscatedForm("adminLogin");
        $this->verifyXSRF($form->key("xsrf"));

        throw new AppControllerException('Whocares');
    }

    /**
     * @throws \App\Common\Exception\ObfuscatedFormsException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Administrator Login')->index(0, 0, 1);

        $form = $this->obfuscatedForms()->get("adminLogin", [
            "xsrf",
            "form",
            "email",
            "password",
            "totp",
            "submit"
        ]);

        $template = $this->template("login.knit")
            ->assign("form", $form->array());
        $this->body($template);
    }
}
