<?php
declare(strict_types=1);

namespace App\Admin\Controllers\App;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Country;
use App\Common\Exception\AppException;
use App\Common\Validator;
use Comely\Database\Exception\DatabaseException;
use Comely\Database\Exception\ORM_Exception;
use Comely\Database\Exception\SchemaException;
use Comely\Database\Schema;

/**
 * Class Countries
 * @package App\Admin\Controllers\App
 */
class Umer extends AbstractAdminController
{
    /** @var array */
    private array $countries;

    /**
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function adminCallback(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Countries');

        try {
            $countries = \App\Common\Database\Primary\Countries::Find()->query("WHERE 1 ORDER BY `name` ASC", [])->all();
        } catch (SchemaException|ORM_Exception $e) {
            $countries = [];
        }
        $this->countries = $countries;
    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Countries')->index(310, 40)
            ->prop("icon", "mdi mdi-earth");

        $this->breadcrumbs("Application", null, "mdi mdi-server-network");

        try {
            $countries = Validator::JSON_Filter($this->countries, "Countries");
        } catch (AppException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            $countries = [];
        }

        $template = $this->template("app/countries.knit")
            ->assign("countries", $countries);
        $this->body($template);
    }
}
