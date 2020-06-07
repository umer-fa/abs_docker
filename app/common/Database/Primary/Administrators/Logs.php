<?php
declare(strict_types=1);

namespace App\Common\Database\Primary\Administrators;

use App\Admin\AppAdmin;
use App\Common\Admin\Log;
use App\Common\Database\AbstractAppTable;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Logs
 * @package App\Common\Database\Primary\Administrators
 */
class Logs extends AbstractAppTable
{
    public const NAME = 'a_logs';
    public const MODEL = 'App\Common\Admin\Log';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(4)->unSigned()->autoIncrement();
        $cols->int("admin")->bytes(4)->unSigned();
        $cols->string("flag")->length(16)->nullable();
        $cols->int("flag_id")->bytes(4)->unSigned()->nullable();
        $cols->string("controller")->length(255)->nullable();
        $cols->string("log")->length(255);
        $cols->string("ip_address")->length(45);
        $cols->int("time_stamp")->bytes(4)->unSigned();
        $cols->primaryKey("id");

        $constraints->foreignKey("admin")->table(Administrators::NAME, "id");
    }

    /**
     * @param int $adminId
     * @param string $message
     * @param string|null $controller
     * @param int|null $line
     * @param string|null $flag
     * @param int|null $flagId
     * @return Log
     * @throws AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\ORM_ModelQueryException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    public static function insert(int $adminId, string $message, ?string $controller = null, ?int $line = null, ?string $flag = null, ?int $flagId = null): Log
    {
        if (!preg_match('/^\w+[\w\s@\-:=.#\",\[\];]+$/', $message)) {
            throw new AppException('Admin log contains an illegal character');
        } elseif (strlen($message) > 255) {
            throw new AppException('Admin log cannot exceed 255 bytes');
        }

        if ($controller && $line) {
            $controller = $controller . "#" . $line;
        }

        $app = AppAdmin::getInstance();
        $db = $app->db()->primary();

        // Prepare Log model
        $log = new Log();
        $log->id = 0;
        $log->admin = $adminId;
        $log->flag = $flag ? substr($flag, 0, 16) : null;
        $log->flagId = $flagId;
        $log->controller = $controller;
        $log->log = $message;
        $log->ipAddress = $app->http()->remote()->ipAddress ?? "";
        $log->timeStamp = time();

        // Insert
        $log->query()->insert(function () {
            throw new AppException('Failed to insert administrator log');
        });

        $log->id = $db->lastInsertId();
        return $log;
    }
}
