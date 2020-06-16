<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Users;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Users\User;
use Comely\Database\Schema;

/**
 * Class Edit
 * @package App\Admin\Controllers\Users
 */
class Edit extends AbstractAdminController
{
    private User $user;

    public function adminCallback(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
        $queryUserId = $this->request()->url()->query();
        var_dump($queryUserId);
    }
}
