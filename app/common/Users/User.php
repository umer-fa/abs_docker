<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Users;
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
    public const CACHE_TTL = 3600;

    /** @var int */
    public int $id;
    /** @var string */
    public string $status;
    /** @var string */
    public string $firstName;
    /** @var string */
    public string $lastName;
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
    private $_credentials = null;
    private $_params = null;
    private $_tally = null;

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
     * @throws \App\Common\Exception\AppConfigException
     */
    public function cipher(): Cipher
    {
        if (!$this->_cipher) {
            try {
                $this->_cipher = $this->app->ciphers()->users()->remix(sprintf("user_%d", $this->id));
            } catch (CipherException $e) {
                $this->app->errors()->triggerIfDebug($e);
                throw new AppException('Failed to retrieve User cipher');
            }
        }

        return $this->_cipher;
    }

    /**
     * @return Binary
     * @throws AppException
     * @throws \App\Common\Exception\AppConfigException
     */
    public function checksum(): Binary
    {
        $raw = sprintf(
            '%d:%s:%s:%d:%s:%s:%d',
            $this->id,
            $this->status,
            $this->email,
            $this->isEmailVerified === 1 ? 1 : 0,
            $this->country,
            $this->phoneSms,
            $this->joinStamp
        );

        return $this->cipher()->pbkdf2("sha1", $raw, 1000);
    }

    /**
     * @throws AppException
     * @throws \App\Common\Exception\AppConfigException
     */
    public function validate(): void
    {
        // Verify checksum
        if ($this->private("checksum") !== $this->checksum()->raw()) {
            throw new AppException(sprintf('User %d checksum verification fail', $this->id));
        }

        $this->_checksumVerified = true;
    }

    public function credentials()
    {

    }

    public function log(string $msg, ?array $data = null, ?string $cnt = null, ?int $line = null, ?array $flag = null)
    {

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
     * @return void
     */
    public function deleteCached(): void
    {
        try {
            $cache = $this->app->cache();
            $cache->delete(sprintf(self::CACHE_KEY, $this->id));
            $cache->delete(sprintf(self::CACHE_KEY_EMAIL, $this->email));
        } catch (CacheException $e) {
        }
    }
}
