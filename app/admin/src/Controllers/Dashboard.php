<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

/**
 * Class Dashboard
 * @package App\Admin\Controllers
 */
class Dashboard extends AbstractAdminController
{
    public function adminCallback(): void
    {

    }

    public function get(): void
    {
        $this->flash()->info('Sign in first?');
    }
}
