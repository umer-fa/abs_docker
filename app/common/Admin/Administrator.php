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
    /** @var string */
    public string $checksum;
    /** @var int */
    public int $status;
    /** @var string */
    public string $email;
    /** @var string|null */
    public ?string $phone = null;
    /** @var string|null */
    public ?string $authToken = null;
    /** @var int */
    public int $timeStamp;

    /** @var Cipher|null */
    private ?Cipher $_cipher = null;
    /** @var Credentials|null */
    private ?Credentials $_cred = null;
    /** @var Privileges|null */
    private ?Privileges $_privileges = null;

    /**
     * @return void
     */
    public function onSerialize(): void
    {
        parent::onSerialize();
        $this->_cipher = null;
        $this->_cred = null;
        $this->_privileges = null;
    }

    public function validate(): void
    {

    }

    public function log(string $msg, ?string $cont = null, ?int $line = null, ?array $flag = null): void
    {

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
                throw new \AppException('Unexpected result after decrypting admin credentials');
            }

            if ($credentials->adminId() !== $this->id) {
                throw new \AppException('Administrator and credentials ID mismatch');
            }

            $this->_cred = $credentials;
            return $this->_cred;
        } catch (\AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new \AppException(
                sprintf('Failed to decrypt administrator %d credentials', $this->id)
            );
        }
    }

    /**
     * @return Privileges
     */
    public function privileges(): Privileges
    {
        if ($this->_privileges) {
            return $this->_privileges;
        }

        try {
            $encrypted = new Binary(strval($this->private("privileges")));
            $privileges = $this->cipher()->decrypt($encrypted);
            if (!$privileges instanceof Privileges) {
                throw new \AppException('Unexpected result after decrypting admin privileges');
            }

            if ($privileges->adminId() !== $this->id) {
                throw new \AppException('Administrator and privileges ID mismatch');
            }

            $this->_privileges = $privileges;
            return $this->_privileges;
        } catch (\AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new \AppException(
                sprintf('Failed to decrypt administrator %d privileges', $this->id)
            );
        }
    }
}
