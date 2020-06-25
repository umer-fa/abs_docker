<?php
declare(strict_types=1);

namespace App\Common\Users;

use Comely\DataTypes\Buffer\Binary;
use Comely\Utils\Security\Exception\PRNG_Exception;
use Comely\Utils\Security\PRNG;

/**
 * Class Params
 * @package App\Common\Users
 */
class Params
{
    /** @var int */
    public int $user;
    /** @var string|null */
    public ?string $emailVerifyBytes = null;
    /** @var string|null */
    public ?string $resetToken = null;
    /** @var int|null */
    public ?int $resetTokenEpoch = null;

    /**
     * Params constructor.
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user->id;
    }

    /**
     * @param int $len
     * @return Binary
     */
    public function setEmailVerifyBytes(int $len = 4): Binary
    {
        if ($len < 4 || $len > 16) {
            throw new \RangeException('E-mail verification token must be 4-16 bytes');
        }

        try {
            $rand = PRNG::randomBytes($len);
        } catch (PRNG_Exception $e) {
            throw new \UnexpectedValueException('Failed to generate random bytes');
        }

        $this->emailVerifyBytes = $rand->raw();
        return $rand->readOnly(true);
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [sprintf('User %d params', $this->user)];
    }
}
