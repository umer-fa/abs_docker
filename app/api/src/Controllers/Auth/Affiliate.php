<?php
declare(strict_types=1);

namespace App\API\Controllers\Auth;

use App\Common\Data\Downlines;

/**
 * Class Affiliate
 * @package App\API\Controllers\Auth
 */
class Affiliate extends AbstractAuthSessAPIController
{
    protected const EXPLICIT_METHOD_NAMES = true;

    public function authSessCallback(): void
    {
    }

    /**
     * @return void
     */
    public function getDownlinesTree(): void
    {
        $downlinesTree = Downlines::getInstance($this->authUser, true);
        $this->status(true);
        $this->response()->set("tree", $downlinesTree->getTree());

        if (isset($downlines->cachedOn)) {
            $this->response()->set("cachedOn", $downlines->cachedOn);
        }
    }

    /**
     * @return void
     */
    public function getDownlines(): void
    {
        $downlines = Downlines::getInstance($this->authUser, true);
        $this->status(true);
        $this->response()->set("downlines", $downlines->getTiers());

        if (isset($downlines->cachedOn)) {
            $this->response()->set("cachedOn", $downlines->cachedOn);
        }
    }
}
