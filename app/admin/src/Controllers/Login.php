<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

use Comely\Http\Router\AbstractController;

/**
 * Class Login
 * @package App\Admin\Controllers
 */
class Login extends AbstractController
{
    public function callback(): void
    {
        var_dump(get_called_class());
        var_dump($this->request());
    }
}
