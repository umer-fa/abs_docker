<?php
declare(strict_types=1);

namespace App\Common\Database\Primary\Users;

use App\Common\Database\AbstractAppTable;
use App\Common\Database\Primary\Users;
use App\Common\Exception\AppException;
use App\Common\Kernel;
use App\Common\Users\User;
use Comely\Database\Exception\DatabaseException;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Tally
 * @package App\Common\Database\Primary\Users
 */
class Tally extends AbstractAppTable
{
    public const NAME = 'u_tally';
    public const MODEL = 'App\Common\Users\Tally';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("user")->bytes(4)->unSigned()->unique();
        $cols->int("last_login")->bytes(4)->unSigned()->nullable();
        $cols->int("last_2fa")->bytes(4)->unSigned()->nullable();
        $cols->string("last_2fa_code")->fixed(6)->nullable();
        $cols->int("last_req_sms")->bytes(4)->unSigned()->nullable();
        $cols->int("last_req_rec")->bytes(4)->unSigned()->nullable();

        $constraints->foreignKey("user")->table(Users::NAME, "id");
    }

    /**
     * @param User $user
     * @return \App\Common\Users\Tally
     * @throws AppException
     */
    public static function User(User $user): \App\Common\Users\Tally
    {
        $k = Kernel::getInstance();

        try {
            $tally = self::Find(["user" => $user->id])->first();
        } catch (DatabaseException $e) {
            if (!$e instanceof ORM_ModelNotFoundException) {
                $k->errors()->triggerIfDebug($e, E_USER_WARNING);
                trigger_error(sprintf('Failed to retrieve user %d tally', $user->id), E_USER_WARNING);
            }
        }

        if (!isset($tally)) {
            try {
                $tally = new \App\Common\Users\Tally();
                $tally->user = $user->id;
                $tally->isNew = true;
            } catch (\Exception $e) {
                $k->errors()->triggerIfDebug($e, E_USER_WARNING);
                throw new AppException(sprintf('Failed to create new user %d tally instance', $user->id));
            }
        }

        return $tally;
    }
}
