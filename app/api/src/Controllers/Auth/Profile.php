<?php
declare(strict_types=1);

namespace App\API\Controllers\Auth;

use App\Common\Database\Primary\Users;

/**
 * Class Profile
 * @package App\API\Controllers\Auth
 */
class Profile extends AbstractAuthSessAPIController
{
    public const EXPLICIT_METHOD_NAMES = true;

    public function authSessCallback(): void
    {
    }

    /**
     * @throws \App\Common\Exception\AppException
     */
    public function getReferrer(): void
    {
        if (!$this->authUser->referrer) {
            $this->status(true);
            $this->response()->set("referrer", null);
            return;
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
