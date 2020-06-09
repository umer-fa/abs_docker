<?php
declare(strict_types=1);

namespace App\Common\Users;

/**
 * Class Params
 * @package App\Common\Users
 */
class Params
{
    /** @var int */
    public int $user;
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
     * @return array
     */
    public function __debugInfo()
    {
        return [sprintf('User %d params', $this->user)];
    }
}
