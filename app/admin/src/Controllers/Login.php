<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

use Comely\Http\Router\AbstractController;

/**
 * Class Login
 * @package App\Admin\Controllers
 */
class Login extends AbstractAdminController
{
    public function adminCallback(): void
    {
    }

    public function get(): void
    {
        var_dump($this->request()->method());
        var_dump(get_called_class());
    }
}
