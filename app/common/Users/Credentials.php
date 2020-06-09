<?php
declare(strict_types=1);

namespace App\Common\Users {

    use App\Common\Users\Credentials\OAuth;

    /**
     * Class Credentials
     * @package App\Common\Users
     */
    class Credentials
    {
        /** @var int */
        public int $user;
        /** @var string|null */
        public ?string $password = null;
        /** @var string|null */
        public ?string $googleAuthSeed = null;
        /** @var OAuth */
        public OAuth $oAuth;

        /**
         * Credentials constructor.
         * @param \App\Common\Users\User $user
         */
        public function __construct(\App\Common\Users\User $user)
        {
            $this->user = $user->id;
            $this->oAuth = new OAuth();
        }

        /**
         * @return array
         */
        public function __debugInfo()
        {
            return [sprintf('User %d credentials', $this->user)];
        }

        /**
         * OAuth on wakeup
         */
        public function __wakeup()
        {
            if (!$this->oAuth instanceof OAuth) {
                $this->oAuth = new OAuth();
            }
        }

        /**
         * @param string $password
         * @return Credentials
         */
        public function hashPassword(string $password): self
        {
            $this->password = password_hash($password, PASSWORD_BCRYPT);
            return $this;
        }

        /**
         * @param string $password
         * @return bool
         */
        public function verifyPassword(string $password): bool
        {
            return password_verify($password, $this->password);
        }
    }
}

namespace App\Common\Users\Credentials {

    /**
     * Class OAuth
     * @package App\Common\Users\Credentials
     */
    class OAuth
    {
        /** @var null|string */
        public ?string $googleId = null;
        /** @var null|string */
        public ?string $facebookId = null;
        /** @var null|string */
        public ?string $linkedInId = null;
    }
}
