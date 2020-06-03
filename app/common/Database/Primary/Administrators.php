<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\Admin\Administrator;
use App\Common\Database\AbstractAppTable;
use App\Common\Exception\AppException;
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

    public static function get(int $adminId): Administrator
    {
        throw new AppException('Failed to get admin account');
    }
}
