<?php
declare(strict_types=1);

namespace App\Common\Database\API;

use App\Common\API\API_Session;
use App\Common\Database\AbstractAppTable;
use App\Common\Database\Primary\Users;
use App\Common\Exception\API_Exception;
use App\Common\Exception\AppException;
use App\Common\Kernel;
use App\Common\Kernel\Databases;
use App\Common\Validator;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;
use Comely\DataTypes\Buffer\Binary;
use Comely\Utils\Security\PRNG;

/**
 * Class Sessions
 * @package App\Common\Database\API
 */
class Sessions extends AbstractAppTable
{
    public const TOKEN_TYPES = ["web", "desktop", "mobile"];
    public const REQUEST_TOKEN_TIMEOUT_PER_IP = 60;

    public const NAME = 'api_sess';
    public const MODEL = 'App\Common\API\API_Session';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @throws AppException
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(8)->unSigned()->autoIncrement();
        $cols->int("archived")->bytes(1)->unSigned()->default(0);
        $cols->enum("type")->options("web", "desktop", "mobile")->default("web");
        $cols->binary("checksum")->fixed(20);
        $cols->binary("token")->fixed(32)->unique();
        $cols->string("ip_address")->length(45);
        $cols->int("auth_user_id")->unSigned()->nullable();
        $cols->int("auth_session_otp")->bytes(1)->unSigned()->nullable();
        $cols->int("recaptcha_last")->bytes(4)->unSigned()->nullable();
        $cols->int("issued_on")->bytes(4)->unSigned();
        $cols->int("last_used_on")->bytes(4)->unSigned();
        $cols->primaryKey("id");

        // Primary Database Name
        $primaryDBName = $this->app->db()->getDbName(Databases::PRIMARY);
        if (!$primaryDBName) {
            throw new AppException('Failed to retrieve PRIMARY database name from config');
        }

        $constraints->foreignKey("auth_user_id")->database($primaryDBName)->table(Users::NAME, "id");
    }

    /**
     * @param Binary|null $token
     * @param string|null $ip
     * @return API_Session
     * @throws API_Exception
     */
    public static function getSession(?Binary $token = null, ?string $ip = null): API_Session
    {
        $k = Kernel::getInstance();

        if ($token) {
            if ($token->sizeInBytes !== 32) {
                throw new \InvalidArgumentException('Invalid token length; Expecting 32 bytes');
            }

            $tokenHex = $token->base16()->hexits(false);
            $memoryId = sprintf('api_sess_%s', $tokenHex);
            $queryCol = "token";
            $queryVal = $token->raw();
        } elseif ($ip) {
            if (!Validator::isValidIP($ip, true)) {
                throw new \InvalidArgumentException('Invalid IP address');
            }

            $memoryId = sprintf('api_sess_%s', md5($ip));
            $queryCol = "ip_address";
            $queryVal = $ip;
        } else {
            throw new \InvalidArgumentException('No token or IP supplied as argument');
        }

        try {
            return $k->memory()->query($memoryId, self::MODEL)
                ->fetch(function () use ($queryCol, $queryVal) {
                    return self::Find()->col($queryCol, $queryVal)->limit(1)->first();
                });
        } catch (\Exception $e) {
            if ($k->isDebug()) {
                if (!$e instanceof ORM_ModelNotFoundException) {
                    throw new API_Exception('SESSION_NOT_FOUND');
                }
            }

            throw new API_Exception('SESSION_RETRIEVE_ERROR');
        }
    }

    /**
     * @param string $ip
     * @param string $type
     * @return Binary
     * @throws API_Exception
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public static function issueToken(string $ip, string $type = "web"): Binary
    {
        $k = Kernel::getInstance();
        $db = $k->db()->apiLogs();

        if ($db->inTransaction()) {
            throw new AppException('API database already in transaction mode');
        }

        if (!in_array($type, self::TOKEN_TYPES)) {
            throw new \InvalidArgumentException('Invalid session token type');
        }

        try {
            $timeStamp = time();
            $secureEntropy = PRNG::randomBytes(32);

            $token = new API_Session();
            $token->id = 0;
            $token->archived = 0;
            $token->set("checksum", "tba");
            $token->type = $type;
            $token->set("token", $secureEntropy->raw());
            $token->ipAddress = $ip;
            $token->authUserId = null;
            $token->recaptchaLast = null;
            $token->issuedOn = $timeStamp;
            $token->lastUsedOn = $timeStamp;

            $db->beginTransaction();

            // Check token is unique
            $tokenUniqueQuery = $db->query()->table(self::NAME)
                ->where('`token`=?', [$secureEntropy->raw()])
                ->limit(1)
                ->fetch();

            if ($tokenUniqueQuery->count()) {
                // Non-unique token, repeat
                return self::issueToken($ip, $type);
            }

            // Requesting Tokens too often?
            $timeOutPerIP = self::REQUEST_TOKEN_TIMEOUT_PER_IP;
            if ($timeOutPerIP) {
                $checkSince = $timeStamp - $timeOutPerIP;
                $lastTokenForIp = $db->query()->table(self::NAME)
                    ->where('`ip_address`=? AND `issued_on`>=?', [$ip, $checkSince])
                    ->fetch();

                if ($lastTokenForIp->count() >= 4) {
                    throw new API_Exception('SESSION_CREATE_TIMEOUT');
                }
            }

            $insert = $token->query()->insert();
            if (!$insert->isSuccess(true) || !is_int($db->lastInsertId())) {
                throw new AppException('Failed to insert API session token in DB');
            }

            $token->id = $db->lastInsertId();
            $token->set("checksum", $token->checksum()->raw());
            $token->query()->where('id', $token->id)->update(function () {
                throw new AppException('Failed to finalise API session row');
            });

            $db->commit();
        } catch (API_Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $k->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new API_Exception('SESSION_CREATE_ERROR');
        }

        return $secureEntropy->readOnly(true);
    }
}
