<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Api;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Config\APIServerAccess;
use App\Common\Exception\AppControllerException;
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

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     * @throws \ReflectionException
     */
    public function post(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck();

        if (!$this->authAdmin->privileges()->root()) {
            if (!$this->authAdmin->privileges()->editConfig) {
                throw new AppControllerException('You are not authorized to update configuration');
            }
        }

        $changes = 0;

        $reflect = new \ReflectionClass($this->apiServerAccess);
        $triggers = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($triggers as $trigger) {
            $propId = $trigger->name;
            $current = $this->apiServerAccess->$propId;
            $this->apiServerAccess->$propId = $this->input()->get($propId) === "on";
            if ($this->apiServerAccess->$propId !== $current) {
                $changes++;
            }
        }

        if (!$changes) {
            throw new AppControllerException('There are no changes to be saved!');
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            // Save configuration in DB
            $this->apiServerAccess->save();

            // Admin Log
            $this->authAdmin->log('API Accessibility Setup Updated', null, null, ["config"]);

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to update API accessibility');
        }

        // Delete Cached
        try {
            $this->app->cache()->delete(APIServerAccess::CACHE_KEY);
        } catch (\Exception $e) {
        }

        $this->response()->set("status", true);
        $this->messages()->success('API accessibility configuration updated!');
        $this->messages()->info('Refreshing page...');
        $this->response()->set("refresh", true);
    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('API Server Configuration')->index(210, 10)
            ->prop("icon", "mdi mdi-access-point-network");

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
