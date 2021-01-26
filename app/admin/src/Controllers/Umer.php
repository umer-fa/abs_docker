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
//        $db = $this->app->db()->primary();
//        Schema::Bind($db, 'App\Common\Database\Primary\Countries');
//        $country = Countries::Find()->query("WHERE 1 ORDER BY `name` ASC",[])->all();
//        $country = Countries::Find()->query("WHERE 1 ORDER BY `name` ASC",[])->first();
//        echo "<pre>";
//        $delete = $db->query()->table("table_name")->where('`status`=:code', ["code" => 1])->delete();
//        $db->query()->table()->insert(['col1'=>'val1','col2'=>'val2']);
//        $object = new Countries();
//        $object->structure();

//        echo sprintf('SELECT `user` FROM `%s`', Countries::NAME);
//        $dt = $db->fetch("SELECT name from countries")->all();
//        $dt = $db->fetch("SELECT name from countries")->count();
//        $dt = $db->fetch("SELECT name from countries")->first();
//        $dt = $db->fetch("SELECT name from countries")->last();
//        $dt = $db->fetch("SELECT name from countries")->current(); //select first only

//        update
//        $statusQuery = $db->query()->table(\App\Common\Database\Primary\Countries::NAME)
//            ->where('`status`=:code', ["code" => 1])
//            ->update(["status" => 2]);
//
//        echo $statusQuery->checkSuccess(true);
//        exit();

//        $dt = $db->fetch("SELECT name from countries");
//        var_dump($dt);
//        exit();
//        $country = Countries::Find()->query("WHERE 1 ORDER BY `name` ASC",[])->match(['name'])->all();
//        var_dump(json_decode(json_encode($country)));
//        exit();

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
        $arr = array("name"=>"umer","id"=>12);
        $template = $this->template("umer.knit")
            ->assign("countries", $arr);
        $this->body($template);
    }
}
