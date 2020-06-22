<?php
declare(strict_types=1);

namespace App\API\Controllers\Auth;

/**
 * Class Totp
 * @package App\API\Controllers\Auth
 */
class Totp extends AbstractAuthSessAPIController
{
    /**
     * @return void
     */
    public function authSessCallback(): void
    {
    }

    /**
     * @throws \App\Common\Exception\AppException
     */
    public function post(): void
    {
        if ($this->apiSession->authSessionOtp) {
            $this->status(true);
            return;
        }


        $this->verifyTOTP(trim(strval($this->input()->get("totp"))));
        $this->apiSession->markAuthSessionOTP();
        $this->status(true);
    }
}
