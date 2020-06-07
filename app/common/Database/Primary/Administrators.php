<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\Admin\Administrator;
use App\Common\Database\AbstractAppTable;
use App\Common\Exception\AppException;
use App\Common\Kernel;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Administrators
 * @package App\Common\Database\Primary
 */
class Administrators extends AbstractAppTable
{
    public const NAME = 'admins';
    public const MODEL = 'App\Common\Admin\Administrator';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(4)->unSigned()->autoIncrement();
        $cols->binary("checksum")->fixed(20);
        $cols->int("status")->bytes(1)->unSigned()->default(1);
        $cols->string("email")->length(32)->unique();
        $cols->string("phone")->length(32)->nullable();
        $cols->binary("credentials")->length(2048);
        $cols->binary("privileges")->length(2048)->nullable();
        $cols->binary("auth_token")->fixed(10)->nullable();
        $cols->int("time_stamp")->bytes(4)->unSigned();
        $cols->primaryKey("id");
    }

    /**
     * @param int $id
     * @return Administrator
     * @throws AppException
     */
    public static function get(int $id): Administrator
    {
        $k = Kernel::getInstance();
        try {
            return $k->memory()->query(sprintf('admin_%d', $id), self::MODEL)
                ->fetch(function () use ($id) {
                    return self::Find()->col("id", $id)->limit(1)->first();
                });
        } catch (\Exception $e) {
            if (!$e instanceof ORM_ModelNotFoundException) {
                $k->errors()->triggerIfDebug($e, E_USER_WARNING);
            }

            throw new AppException('Failed to retrieve Administrator account');
        }
    }

    /**
     * @param string $em
     * @return Administrator
     * @throws AppException
     */
    public static function email(string $em): Administrator
    {
        $k = Kernel::getInstance();
        try {
            return $k->memory()->query(sprintf('admin_%s', $em), self::MODEL)
                ->fetch(function () use ($em) {
                    return self::Find()->col("email", $em)->limit(1)->first();
                });
        } catch (\Exception $e) {
            if (!$e instanceof ORM_ModelNotFoundException) {
                $k->errors()->triggerIfDebug($e, E_USER_WARNING);
            }

            throw new AppException('No such administrator account with this e-mail');
        }
    }
}
