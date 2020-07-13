<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\Database\AbstractAppTable;
use App\Common\Exception\AppException;
use App\Common\Kernel;
use App\Common\Users\User;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Users
 * @package App\Common\Database\Primary
 */
class Users extends AbstractAppTable
{
    public const NAME = 'users';
    public const MODEL = 'App\Common\Users\User';
    public const BINARY_OBJ_SIZE = 4096;

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(4)->unSigned()->autoIncrement();
        $cols->binary("checksum")->fixed(20);
        $cols->int("referrer")->bytes(4)->unSigned()->nullable();
        $cols->enum("status")->options("active", "frozen", "disabled")->default("active");
        $cols->string("first_name")->length(32)
            ->charset("utf8mb4")->collation("utf8mb4_general_ci");
        $cols->string("last_name")->length(32)
            ->charset("utf8mb4")->collation("utf8mb4_general_ci");
        $cols->string("username")->length(20)->unique();
        $cols->string("email")->length(64)->unique();
        $cols->int("is_email_verified")->bytes(1)->default(0);
        $cols->string("country")->fixed(3);
        $cols->string("phone_sms")->length(24)->nullable();
        $cols->binary("credentials")->length(self::BINARY_OBJ_SIZE);
        $cols->binary("params")->length(self::BINARY_OBJ_SIZE);
        $cols->binary("auth_token")->fixed(32)->nullable();
        $cols->binary("auth_api_hmac")->fixed(16)->nullable();
        $cols->int("join_stamp")->bytes(4)->unSigned();
        $cols->int("time_stamp")->bytes(4)->unSigned();
        $cols->primaryKey("id");

        $constraints->foreignKey("referrer")->table(self::NAME, "id");
        $constraints->foreignKey("country")->table(Countries::NAME, "code");
    }

    /**
     * @param int $id
     * @param bool $cache
     * @return User
     * @throws AppException
     */
    public static function get(int $id, bool $cache = true): User
    {
        $k = Kernel::getInstance();

        try {
            return self::CachedSearch(sprintf(User::CACHE_KEY, $id), "id", $id, $cache);
        } catch (\Exception $e) {
            if ($e instanceof ORM_ModelNotFoundException) {
                throw AppException::ModelNotFound(sprintf('No such user with id #%d exists', $id));
            }

            $k->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(sprintf('Failed to retrieve user by ID %d', $id));
        }
    }

    /**
     * @param string $email
     * @param bool $cache
     * @return User
     * @throws AppException
     */
    public static function Email(string $email, bool $cache = true): User
    {
        $k = Kernel::getInstance();

        try {
            $cacheId = sprintf(User::CACHE_KEY_EMAIL, md5(strtolower($email)));
            return self::CachedSearch($cacheId, "email", $email, $cache);
        } catch (\Exception $e) {
            if ($e instanceof ORM_ModelNotFoundException) {
                throw AppException::ModelNotFound('E-mail address is not registered');
            }

            $k->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to retrieve user by E-mail address');
        }
    }

    /**
     * @param string $username
     * @param bool $cache
     * @return User
     * @throws AppException
     */
    public static function Username(string $username, bool $cache = true): User
    {
        $k = Kernel::getInstance();

        try {
            return self::CachedSearch(sprintf(User::CACHE_KEY_USERNAME, strtolower($username)), "username", $username, $cache);
        } catch (\Exception $e) {
            if ($e instanceof ORM_ModelNotFoundException) {
                throw AppException::ModelNotFound('Username is not registered');
            }

            $k->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to retrieve user by Username');
        }
    }

    /**
     * @param string $cacheId
     * @param string $colName
     * @param $arg
     * @param bool $cache
     * @return User
     */
    private static function CachedSearch(string $cacheId, string $colName, $arg, bool $cache = true): User
    {
        $k = Kernel::getInstance();
        $query = $k->memory()->query($cacheId, self::MODEL);
        if ($cache) {
            $query->cache(User::CACHE_TTL);
        }

        /** @var User $user */
        $user = $query->fetch(function () use ($colName, $arg) {
            return self::Find()->col($colName, $arg)->limit(1)->first();
        });

        return $user;
    }
}
