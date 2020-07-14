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
        $cols->string("flags")->length(128)->nullable();
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
     * @param array|null $flags
     * @return Log
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     *
     */
    public static function insert(int $adminId, string $message, ?string $controller = null, ?int $line = null, ?array $flags = null): Log
    {
        if (!preg_match('/^\w+[\w\s@\-:=.#\",()\[\];]+$/', $message)) {
            throw new AppException('Admin log contains an illegal character');
        } elseif (strlen($message) > 255) {
            throw new AppException('Admin log cannot exceed 255 bytes');
        }

        $logFlags = null;
        if ($flags) {
            $logFlags = [];
            $flagIndex = -1;
            foreach ($flags as $flag) {
                $flagIndex++;
                if (!preg_match('/^\w{1,16}(:[0-9]{1,10})?$/', $flag)) {
                    throw new AppException(sprintf('Invalid admin log flag at index %d', $flagIndex));
                }

                $logFlags[] = $flag;
            }

            $logFlags = implode(",", $logFlags);
            if (strlen($logFlags) > 128) {
                throw new AppException('Admin log flags exceed limit of 128 bytes');
            }
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
        $log->flags = $logFlags;
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
