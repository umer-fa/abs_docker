<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Country;
use App\Common\Database\Primary\Countries;
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
//        $country = Countries::Find()->query("WHERE 1 ORDER BY `name` ASC",[])->all();
//        $country = Countries::Find()->query("WHERE 1 ORDER BY `name` ASC",[])->first();
        $country = Countries::Find()->query("WHERE 1 ORDER BY `name` ASC",[])->match(['name'])->all();
        echo '<pre>';
        var_dump(json_decode(json_encode($country)));
        exit();
    }

    /**
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DbConnectionException
     */

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        echo 'umer';exit();
    }
}
