<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Api;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Config\APIServerAccess;
use App\Common\Exception\AppException;
use Comely\Utils\Time\Time;
use Comely\Utils\Time\TimeUnits;

/**
 * Class Access
 * @package App\Admin\Controllers\Api
 */
class Access extends AbstractAdminController
{
    /** @var APIServerAccess */
    private APIServerAccess $apiServerAccess;

    /**
     * @return void
     */
    public function adminCallback(): void
    {
        try {
            $this->apiServerAccess = APIServerAccess::getInstance(true);
        } catch (AppException $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
            $this->apiServerAccess = new APIServerAccess();
        }
    }

    public function post(): void
    {

    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('API Server Configuration')->index(4, 10)
            ->prop("icon", "fa fa-universal-access");

        $this->breadcrumbs("API Server", null, "ion ion-ios-cloud");


        // Last Cached On
        $lastCachedOn = null;
        if (isset($this->apiServerAccess->cachedOn)) {
            $lastCachedTimeDiff = Time::difference($this->apiServerAccess->cachedOn);
            if ($lastCachedTimeDiff) {
                $timeUnits = new TimeUnits();
                $lastCachedOn = $timeUnits->timeToString($lastCachedTimeDiff);
            }
        }

        $template = $this->template("api/access.knit")
            ->assign("lastCachedOn", $lastCachedOn)
            ->assign("apiServerAccess", $this->apiServerAccess);
        $this->body($template);
    }
}
