<?php
declare(strict_types=1);

namespace App\API\Controllers\Auth;


use App\Common\Database\Primary\Users;

/**
 * Class Referrer
 * @package App\API\Controllers\Auth
 */
class Referrer extends AbstractAuthSessAPIController
{
    public function authSessCallback(): void
    {
    }

    /**
     * @throws \App\Common\Exception\AppException
     */
    public function get(): void
    {
        if (!$this->authUser->referrer) {
            $this->status(true);
            $this->response()->set("referrer", null);
        }

        $referrer = Users::get($this->authUser->referrer);
        $referrer->validate();

        $this->status(true);
        $this->response()->set("referrer", [
            "username" => $referrer->username,
            "firstName" => $referrer->firstName,
            "lastName" => $referrer->lastName,
            "country" => $referrer->country,
        ]);
    }
}
