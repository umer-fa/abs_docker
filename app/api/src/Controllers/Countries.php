<?php
declare(strict_types=1);

namespace App\API\Controllers;

use Comely\Database\Schema;

/**
 * Class Countries
 * @package App\API\Controllers
 */
class Countries extends AbstractSessionAPIController
{
    /**
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function sessionAPICallback(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Countries');
    }

    /**
     * @return void
     */
    public function get(): void
    {
        $countries = \App\Common\Database\Primary\Countries::List(1);

        $this->status(true);
        $this->response()->set("countries", $countries);
    }
}
