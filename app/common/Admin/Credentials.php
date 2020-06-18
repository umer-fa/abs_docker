<?php
declare(strict_types=1);

namespace App\Common\Admin;

use App\Common\Packages\GoogleAuth\GoogleAuthenticator;

/**
 * Class Credentials
 * @package App\Common\Admin
 */
class Credentials
{
    /** @var int Administrator's ID */
    private int $id;
    /** @var null|string */
    private ?string $password = null;
    /** @var null|string */
    private ?string $googleAuthSeed = null;

    /**
     * Credentials constructor.
     * @param Administrator $admin
     */
    public function __construct(Administrator $admin)
    {
        $this->id = $admin->id;
    }

    /**
     * @return int
     */
    public function adminId(): int
    {
        return $this->id;
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        return [sprintf('Administrator %d credentials', $this->id)];
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * @param string $potentialPassword
     * @return bool
     */
    public function verifyPassword(string $potentialPassword): bool
    {
        return password_verify($potentialPassword, strval($this->password));
    }

    /**
     * @param string|null $seed
     */
    public function setGoogleAuthSeed(?string $seed): void
    {
        $this->googleAuthSeed = $seed;
    }

    /**
     * @return string|null
     */
    public function getGoogleAuthSeed(): ?string
    {
        return $this->googleAuthSeed;
    }

    /**
     * @param string $code
     * @return bool
     */
    public function verifyTotp(string $code): bool
    {
        if (!$code || !$this->googleAuthSeed) {
            return false;
        }

        $googleAuth = new GoogleAuthenticator($this->googleAuthSeed);
        return $googleAuth->verify($code);
    }
}
