<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Users;
use App\Common\Exception\AppConfigException;
use App\Common\Exception\AppException;
use App\Common\Validator;
use Comely\Cache\Exception\CacheException;
use Comely\DataTypes\Buffer\Binary;
use Comely\Utils\Security\Cipher;
use Comely\Utils\Security\Exception\CipherException;

/**
 * Class User
 * @package App\Common\Users
 */
class User extends AbstractAppModel
{
    public const TABLE = Users::NAME;
    public const SERIALIZABLE = true;

    public const CACHE_KEY = 'user_%d';
    public const CACHE_KEY_EMAIL = 'user_em_%s';
    public const CACHE_KEY_USERNAME = 'username_%s';
    public const CACHE_TTL = 3600;

    /** @var int */
    public int $id;
    /** @var int|null */
    public ?int $referrer = null;
    /** @var string */
    public string $status;
    /** @var string */
    public string $firstName;
    /** @var string */
    public string $lastName;
    /** @var string */
    public string $username;
    /** @var string */
    public string $email;
    /** @var int */
    public int $isEmailVerified;
    /** @var string */
    public string $country;
    /** @var null|string */
    public ?string $phoneSms = null;
    /** @var int */
    public int $joinStamp;
    /** @var int */
    public int $timeStamp;

    /** @var Cipher|null */
    private ?Cipher $_cipher = null;
    /** @var bool|null */
    public ?bool $_checksumVerified = null;
    /** @var Credentials|null */
    private ?Credentials $_credentials = null;
    /** @var Params|null */
    private ?Params $_params = null;
    /** @var Tally|null */
    private ?Tally $_tally = null;

    /**
     * @throws AppException
     */
    public function beforeQuery()
    {
        $credLen = strlen($this->private("credentials"));
        if ($credLen >= Users::BINARY_OBJ_SIZE) {
            throw new AppException(sprintf('User column "credentials" exceeds limit of %d bytes', Users::BINARY_OBJ_SIZE));
        }

        $paramsLen = strlen($this->private("params"));
        if ($paramsLen >= Users::BINARY_OBJ_SIZE) {
            throw new AppException(sprintf('User column "params" exceeds limit of %d bytes', Users::BINARY_OBJ_SIZE));
        }
    }

    /**
     * @return void
     */
    public function onSerialize(): void
    {
        parent::onSerialize();
        $this->_credentials = null;
        $this->_params = null;
        $this->_checksumVerified = null;
        $this->_tally = null;
        $this->_cipher = null;
    }

    /**
     * @return Cipher
     * @throws AppException
     */
    public function cipher(): Cipher
    {
        if (!$this->_cipher) {
            try {
                $this->_cipher = $this->app->ciphers()->users()->remix(sprintf("user_%d", $this->id));
            } catch (AppConfigException|CipherException $e) {
                $this->app->errors()->triggerIfDebug($e);
                throw new AppException('Failed to retrieve User cipher');
            }
        }

        return $this->_cipher;
    }

    /**
     * @return Binary
     * @throws AppException
     */
    public function checksum(): Binary
    {
        $raw = sprintf(
            '%d:%d:%s:%s:%s:%d:%s:%s:%d',
            is_int($this->referrer) && $this->referrer > 0 ? $this->referrer : 0,
            $this->id,
            $this->status,
            $this->email,
            $this->username,
            $this->isEmailVerified === 1 ? 1 : 0,
            $this->country,
            $this->phoneSms,
            $this->joinStamp
        );

        return $this->cipher()->pbkdf2("sha1", $raw, 1000);
    }

    /**
     * @throws AppException
     */
    public function validate(): void
    {
        // Verify checksum
        if ($this->private("checksum") !== $this->checksum()->raw()) {
            throw new AppException(sprintf('User %d checksum verification fail', $this->id));
        }

        $this->_checksumVerified = true;
    }

    /**
     * @return Credentials
     * @throws AppException
     */
    public function credentials(): Credentials
    {
        if ($this->_credentials) {
            return $this->_credentials;
        }

        try {
            $encrypted = new Binary(strval($this->private("credentials")));
            $credentials = $this->cipher()->decrypt($encrypted);
            if (!$credentials instanceof Credentials) {
                throw new AppException('Unexpected result after decrypting user credentials');
            }

            if ($credentials->user !== $this->id) {
                throw new AppException('Credentials and user IDs mismatch');
            }

            $this->_credentials = $credentials;
            return $this->_credentials;
        } catch (AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(
                sprintf('Failed to decrypt user %d credentials', $this->id)
            );
        }
    }

    /**
     * @return Params
     * @throws AppException
     */
    public function params(): Params
    {
        if ($this->_params) {
            return $this->_params;
        }

        try {
            $encrypted = new Binary(strval($this->private("params")));
            $params = $this->cipher()->decrypt($encrypted);
            if (!$params instanceof Params) {
                throw new AppException('Unexpected result after decrypting user params');
            }

            if ($params->user !== $this->id) {
                throw new AppException('Params and user IDs mismatch');
            }

            $this->_params = $params;
            return $this->_params;
        } catch (AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(
                sprintf('Failed to decrypt user %d params', $this->id)
            );
        }
    }

    /**
     * @return Tally
     * @throws AppException
     */
    public function tally(): Tally
    {
        if (!$this->_tally) {
            $this->_tally = Users\Tally::User($this);
        }

        return $this->_tally;
    }

    /**
     * @param string $msg
     * @param array|null $data
     * @param string|null $cnt
     * @param int|null $line
     * @param array|null $flags
     * @return Log
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function log(string $msg, ?array $data = null, ?string $cnt = null, ?int $line = null, ?array $flags = null): Log
    {
        return Users\Logs::insert($this->id, $msg, $data, $cnt, $line, $flags);
    }

    /**
     * @return string|null
     * @throws AppException
     */
    public function smsPhoneNum(): ?string
    {
        if (!$this->phoneSms) {
            return null;
        }

        if (!Validator::isValidPhone($this->phoneSms)) {
            throw new AppException(sprintf('Invalid user # %d SMS phone number', $this->id));
        }

        return $this->phoneSms;
    }

    /**
     * @return Binary
     * @throws AppException
     */
    public function emailVerifyBytes(): Binary
    {
        return $this->cipher()->pbkdf2("sha1", $this->email, 0x21a);
    }

    /**
     * @return void
     */
    public function deleteCached(): void
    {
        try {
            $cache = $this->app->cache();
            $cache->delete(sprintf(self::CACHE_KEY, $this->id));
            $cache->delete(sprintf(self::CACHE_KEY_USERNAME, strtolower($this->username)));
            $cache->delete(sprintf(self::CACHE_KEY_EMAIL, md5(strtolower($this->email))));
        } catch (CacheException $e) {
        }
    }
}
