<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

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
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Administrator Login')->index(0, 0, 1);

        var_dump($this->session()->id());

        $template = $this->template("login.knit");
        $this->body($template);
    }
}
