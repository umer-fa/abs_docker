<?php
declare(strict_types=1);

namespace App\Common\Database\API;

use App\Common\Database\AbstractAppTable;
use App\Common\Database\Primary\Users;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Queries
 * @package App\Common\Database\API
 */
class Queries extends AbstractAppTable
{
    public const NAME = 'api_queries';
    public const MODEL = 'App\Common\API\Query';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(8)->unSigned()->autoIncrement();
        $cols->binary("checksum")->fixed(20);
        $cols->string("ip_address")->length(45);
        $cols->string("method")->length(8);
        $cols->string("endpoint")->length(512);
        $cols->double("start_on")->precision(14, 4)->unSigned();
        $cols->double("end_on")->precision(14, 4)->unSigned();
        $cols->int("res_code")->bytes(2)->unSigned()->nullable();
        $cols->int("res_len")->bytes(4)->unSigned()->nullable();
        $cols->binary("flag_api_sess")->fixed(32)->nullable();
        $cols->int("flag_user_id")->bytes(4)->unSigned()->nullable();
        $cols->primaryKey("id");

        // Primary database name
        $primaryDbName = $this->app->db()->primary()->credentials()->name;

        $constraints->foreignKey("flag_api_sess")->table(Sessions::NAME, "token");
        $constraints->foreignKey("flag_user_id")->database($primaryDbName)->table(Users::NAME, "id");
    }
}
