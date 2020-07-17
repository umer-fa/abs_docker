<?php
declare(strict_types=1);

namespace App\Common\API;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\API\Sessions;
use App\Common\Database\Primary\Users;
use App\Common\Exception\APIAuthException;
use App\Common\Exception\AppException;
use App\Common\Users\User;
use Comely\Database\Exception\DbQueryException;
use Comely\DataTypes\Buffer\Base16;
use Comely\DataTypes\Buffer\Binary;
use Comely\Utils\Time\Time;

/**
 * Class API_Session
 * @package App\Common\API
 */
class API_Session extends AbstractAppModel
{
    public const TABLE = Sessions::NAME;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var int */
    public int $archived;
    /** @var string */
    public string $type;
    /** @var string */
    public string $ipAddress;
    /** @var null|int */
    public ?int $authUserId = null;
    /** @var null|int */
    public ?int $authSessionOtp = null;
    /** @var null|int */
    public ?int $recaptchaLast = null;
    /** @var int */
    public int $issuedOn;
    /** @var int */
    public int $lastUsedOn;

    /** @var APISessBaggage|null */
    private ?APISessBaggage $_baggage = null;
    /** @var null|User */
    private ?User $_authUser = null;
    /** @var Base16|null */
    private ?Base16 $_tokenHex = null;
    /** @var int|null */
    public ?int $_lastUsedOn = null;
    /** @var bool|null */
    public ?bool $_checksumValidated = null;

    /**
     * @return Base16
     * @throws AppException
     */
    public function token(): Base16
    {
        if (!$this->_tokenHex) {
            $token = $this->private("token");
            if (!$token) {
                throw new AppException('API session token undefined');
            }

            $this->_tokenHex = (new Binary($token))->base16()->readOnly(true);
        }

        return $this->_tokenHex;
    }

    /**
     * @return Binary
     * @throws \App\Common\Exception\AppConfigException
     */
    public function checksum(): Binary
    {
        $token = $this->private("token");
        if (!is_string($token) || !$token) {
            throw new \UnexpectedValueException('API session has no token ID');
        }

        $raw = sprintf(
            '%d:%s:%s:%s:%d:%d',
            $this->id,
            $this->type,
            $token,
            $this->ipAddress,
            $this->authUserId ?? 0,
            $this->authSessionOtp === 1 ? 1 : 0
        );

        return $this->app->ciphers()->users()->pbkdf2("sha1", $raw, 1000);
    }

    /**
     * @throws AppException
     * @throws \App\Common\Exception\AppConfigException
     */
    public function validate(): void
    {
        $token = $this->private("token");
        if (!is_string($token) || !$token) {
            throw new \UnexpectedValueException('API session has no token ID');
        }

        $checksum = $this->private("checksum") ?? null;
        if ($this->checksum()->raw() !== $checksum) {
            throw new AppException('SESSION_CHECKSUM_FAIL');
        }

        $this->_checksumValidated = true;
    }

    /**
     * @param bool $forceRecheck
     * @return User
     * @throws APIAuthException
     * @throws AppException
     */
    public function authenticate(bool $forceRecheck = false): User
    {
        if ($this->_authUser && !$forceRecheck) {
            return $this->_authUser;
        }

        if (!$this->authUserId) {
            throw new APIAuthException('AUTH_NOT_LOGGED_IN');
        }

        try {
            try {
                $user = Users::get($this->authUserId);
                $user->validate();
                $user->credentials();
            } catch (AppException $e) {
                $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
                throw new APIAuthException('AUTH_USER_RETRIEVE_ERROR');
            }

            // Is current session
            if ($this->private("token") !== $user->private("authToken")) {
                throw new APIAuthException('AUTH_TOKEN_MISMATCH');
            }

            // Check status
            if (!in_array($user->status, ["active", "frozen"])) {
                throw new APIAuthException('AUTH_USER_DISABLED');
            }

            // Check Timeout
            if (is_int($this->_lastUsedOn) && Time::difference($this->_lastUsedOn) >= 43200) {
                try {
                    $user->log('session-timeout', null, null, null, ["auth"]);
                } catch (\Exception $e) {
                    $this->app->errors()->triggerIfDebug($e);
                    throw new AppException('Failed to create user session timeout log');
                }

                throw new APIAuthException('AUTH_USER_TIMEOUT');
            }

            // Set User object as Auth
            $this->_authUser = $user;
            return $this->_authUser;
        } catch (APIAuthException $e) {
            // Logout
            try {
                $this->logout();
            } catch (AppException $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }

            throw $e;
        }
    }

    /**
     * @param User $user
     * @param bool $archivePrevSessions
     * @return bool
     * @throws AppException
     */
    public function loginAs(User $user, bool $archivePrevSessions = true): bool
    {
        try {
            $this->authUserId = $user->id;
            $this->authSessionOtp = null;
            $this->set("checksum", $this->checksum()->raw());
            $this->query()->where("id", $this->id)->update(function () {
                throw new AppException('Failed to authenticate current session');
            });

            $user->deleteCached();

            // Archive all previous tokens
            if ($archivePrevSessions) {
                $apiDb = $this->app->db()->apiLogs();

                try {
                    $apiDb->exec(
                        sprintf('UPDATE `%s` SET `archived`=1 WHERE `auth_user_id`=? AND `id`<?', Sessions::NAME),
                        [$user->id, $this->id]
                    );
                } catch (DbQueryException $e) {
                    throw new AppException('Failed to archive previous sessions from this user');
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('API session login logic fail');
        }
    }

    /**
     * @throws AppException
     */
    public function logout(): void
    {
        try {
            $this->authUserId = null;
            $this->set("checksum", $this->checksum()->raw());
            $this->query()->where("id", $this->id)->update(function () {
                throw new AppException('Failed to un-authenticate current session');
            });
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('API session logout fail');
        }
    }

    /**
     * @return bool
     * @throws AppException
     */
    public function markAuthSessionOTP(): bool
    {
        if (!$this->authUserId) {
            throw new AppException('Cannot use method without setting authUserId');
        }

        try {
            $this->authSessionOtp = 1;
            $this->set("checksum", $this->checksum()->raw());
            $this->query()->where("id", $this->id)->update(function () {
                throw new AppException('Failed to OTP authenticate current session');
            });

            return true;
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('API session OTP authenticate logic fail');
        }
    }

    /**
     * @return APISessBaggage
     */
    public function baggage(): APISessBaggage
    {
        if ($this->_baggage) {
            return $this->_baggage;
        }

        $this->_baggage = new APISessBaggage($this);
        return $this->_baggage;
    }
}
