<?php
declare(strict_types=1);

namespace App\Common\Database\Primary\Users;

use App\Common\Database\AbstractAppTable;
use App\Common\Database\Primary\Users;
use App\Common\Exception\AppException;
use App\Common\Kernel;
use App\Common\Users\Log;
use Comely\Database\Queries\Query;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Logs
 * @package App\Common\Database\Primary\Users
 */
class Logs extends AbstractAppTable
{
    public const NAME = 'u_logs';
    public const MODEL = 'App\Common\Users\Log';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(4)->unSigned()->autoIncrement();
        $cols->int("user")->bytes(4)->unSigned();
        $cols->string("flag")->length(16)->nullable();
        $cols->int("flag_id")->bytes(4)->unSigned()->default(0);
        $cols->string("controller")->length(255)->nullable();
        $cols->string("log")->length(64);
        $cols->string("data")->length(255)->nullable();
        $cols->string("ip_address")->length(45);
        $cols->int("time_stamp")->bytes(4)->unSigned();
        $cols->primaryKey("id");

        $constraints->foreignKey("user")->table(Users::NAME, "id");
    }

    /**
     * @param int $user
     * @param string $message
     * @param array|null $data
     * @param string|null $controller
     * @param int|null $line
     * @param string|null $flag
     * @param int|null $flagId
     * @return Log
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public static function insert(int $user, string $message, ?array $data = null, ?string $controller = null, ?int $line = null, ?string $flag = null, ?int $flagId = null): Log
    {
        // Log
        if (!preg_match('/^[\w]+[\w\-.]+$/', $message)) {
            throw new AppException('Invalid log format');
        } elseif (strlen($message) >= 64) {
            throw new AppException('Log cannot exceed 64 bytes');
        }

        // Data
        if ($data) {
            $encodedData = base64_encode(json_encode(array_values($data)));
            if (strlen($encodedData) >= 255) {
                throw new AppException('Log data cannot exceed 255 bytes');
            }
        }

        // Controller
        if ($controller) {
            if (!preg_match('/^[\w\\\\]+(::[\w]+)?$/', $controller)) {
                throw new AppException('Invalid log controller name');
            }

            if ($line) {
                $controller = sprintf('%s:%d', $controller, $line);
            }
        }

        // App
        $app = Kernel\AbstractHttpApp::getInstance();
        $db = $app->db()->primary();

        // Prepare Log
        $log = new Log();
        $log->id = 0;
        $log->user = $user;
        $log->flag = $flag ? substr($flag, 0, 16) : null;
        $log->flagId = $flagId ?? 0;
        $log->controller = $controller;
        $log->log = $message;
        $log->data = $encodedData ?? null;
        $log->ipAddress = $app->http()->remote()->ipAddress ?? "0.0.0.0";
        $log->timeStamp = time();

        // Insert
        $log->query()->insert(function (Query $insertQuery) use ($app) {
            if ($app->isDebug() && $insertQuery->error()) {
                $app->errors()->trigger($insertQuery->error()->info, E_USER_WARNING);
            }

            throw new AppException('Failed to insert user log');
        });

        $log->id = $db->lastInsertId();
        if ($log->id < 1) {
            throw new AppException('Failed to retrieve inserted user log Id');
        }

        return $log;
    }
}
