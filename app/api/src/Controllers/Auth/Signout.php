<?php
declare(strict_types=1);

namespace App\API\Controllers\Auth;

use App\Common\Exception\AppException;
use Comely\Database\Exception\DatabaseException;

/**
 * Class Signout
 * @package App\API\Controllers\Auth
 */
class Signout extends AbstractAuthSessAPIController
{
    /**
     * @throws AppException
     */
    public function authSessCallback(): void
    {
        $this->apiSession->logout();

        try {
            $this->authUser->log("signout", null, null, null, ["auth"]);
        } catch (DatabaseException $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
        }

        $this->status(true);
    }

    /**
     * @return void
     */
    public function get(): void
    {
    }

    /**
     * @return void
     */
    public function post(): void
    {
    }
}
