<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Users;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Database\Primary\Countries;
use Comely\Database\Schema;

/**
 * Class Add
 * @package App\Admin\Controllers\Users
 */
class Add extends AbstractAdminController
{
    /**
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function adminCallback(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Countries');
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
    }

    public function post(): void
    {

    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Create User')->index(1100, 20)
            ->prop("icon", "mdi mdi-account-add");

        $this->breadcrumbs("Users Control", null, "ion ion-ios-people");

        // Countries
        try {
            $countries = Countries::Find()->query('WHERE 1 ORDER BY `name` ASC')->all();
        } catch (\Exception $e) {
            $this->app->errors()->trigger($e);
        }

        $template = $this->template("users/add.knit")
            ->assign("countries", isset($countries) && is_array($countries) ? $countries : []);
        $this->body($template);
    }
}
