<?php
declare(strict_types=1);

namespace App\Common\Admin;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use Comely\DataTypes\Buffer\Binary;
use Comely\Utils\Security\Cipher;

/**
 * Class Administrator
 * @package App\Common\Admin
 */
class Administrator extends AbstractAppModel
{
    public const TABLE = Administrators::NAME;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var int */
    public int $status;
    /** @var string */
    public string $email;
    /** @var string|null */
    public ?string $phone = null;
    /** @var int */
    public int $timeStamp;

    /** @var Cipher|null */
    private ?Cipher $_cipher = null;
    /** @var Credentials|null */
    private ?Credentials $_cred = null;
    /** @var Privileges|null */
    private ?Privileges $_privileges = null;
    /** @var bool|null */
    public ?bool $_checksumVerified = null;

    /**
     * @return void
     */
    public function onSerialize(): void
    {
        parent::onSerialize();
        $this->_cipher = null;
        $this->_cred = null;
        $this->_privileges = null;
        $this->_checksumVerified = null;
    }

    /**
     * @throws AppException
     */
    public function validate(): void
    {
        // Verify checksum
        if ($this->checksum()->raw() !== $this->private("checksum")) {
            throw new AppException('Administrator checksum verification fail');
        }

        $this->_checksumVerified = true;
    }

    /**
     * @return Binary
     * @throws AppException
     */
    public function checksum(): Binary
    {
        $raw = sprintf('%d:%d:%s:%s', $this->id, $this->status, trim($this->email), trim($this->phone ?? ""));
        return $this->cipher()->pbkdf2("sha1", $raw, 1000);
    }

    /**
     * @param string $msg
     * @param string|null $cont
     * @param int|null $line
     * @param array|null $flags
     * @return Log
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function log(string $msg, ?string $cont = null, ?int $line = null, ?array $flags = null): Log
    {
        return Administrators\Logs::insert($this->id, $msg, $cont, $line, $flags);
    }

    /**
     * @return Cipher
     * @throws AppException
     */
    public function cipher(): Cipher
    {
        if (!$this->_cipher) {
            try {
                $this->_cipher = $this->app->ciphers()->primary()->remix(sprintf('admin_%d', $this->id));
            } catch (\Exception $e) {
                $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
                throw new AppException(sprintf('Cannot retrieve admin %d cipher', $this->id));
            }
        }

        return $this->_cipher;
    }

    /**
     * @return Credentials
     * @throws AppException
     */
    public function credentials(): Credentials
    {
        if ($this->_cred) {
            return $this->_cred;
        }

        try {
            $encrypted = new Binary(strval($this->private("credentials")));
            $credentials = $this->cipher()->decrypt($encrypted);
            if (!$credentials instanceof Credentials) {
                throw new AppException('Unexpected result after decrypting admin credentials');
            }

            if ($credentials->adminId() !== $this->id) {
                throw new AppException('Administrator and credentials ID mismatch');
            }

            $this->_cred = $credentials;
            return $this->_cred;
        } catch (AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(
                sprintf('Failed to decrypt administrator %d credentials', $this->id)
            );
        }
    }

    /**
     * @return Privileges
     * @throws AppException
     */
    public function privileges(): Privileges
    {
        if ($this->_privileges) {
            return $this->_privileges;
        }

        $encrypted = $this->private("privileges");
        if (!is_string($encrypted) || !$encrypted) {
            $this->_privileges = new Privileges($this);
            return $this->_privileges;
        }

        try {
            $encrypted = new Binary($encrypted);
            $privileges = $this->cipher()->decrypt($encrypted);
            if (!$privileges instanceof Privileges) {
                throw new AppException('Unexpected result after decrypting admin privileges');
            }

            if ($privileges->adminId() !== $this->id) {
                throw new AppException('Administrator and privileges ID mismatch');
            }

            $this->_privileges = $privileges;
            return $this->_privileges;
        } catch (AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(
                sprintf('Failed to decrypt administrator %d privileges', $this->id)
            );
        }
    }
}
