<?php
declare(strict_types=1);

namespace App\Admin\Controllers\App;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Config\APIServerAccess;
use App\Common\Config\ProgramConfig;
use App\Common\Config\SMTPConfig;
use App\Common\Exception\AppControllerException;
use Comely\Cache\Cache;
use Comely\Cache\Exception\CacheException;
use Comely\Utils\Time\Time;
use Comely\Utils\Time\TimeUnits;

/**
 * Class Caching
 * @package App\Admin\Controllers\App
 */
class Caching extends AbstractAdminController
{
    /** @var Cache|null */
    private ?Cache $cache = null;

    /**
     * @return void
     */
    public function adminCallback(): void
    {
        try {
            $this->cache = $this->app->cache();
            $this->cache->ping();
        } catch (CacheException $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
        }
    }

    /**
     * @return array
     */
    private function cachedObjects(): array
    {
        $cachedObjects = [];
        $cachedObjects[] = [
            "name" => "API Server Accessibility",
            "key" => APIServerAccess::CACHE_KEY,
            "size" => null,
            "cachedOn" => null,
            "expiresIn" => null,
            "age" => null
        ];

        $cachedObjects[] = [
            "name" => "Program Configuration",
            "key" => ProgramConfig::CACHE_KEY,
            "size" => null,
            "cachedOn" => null,
            "expiresIn" => null,
            "age" => null
        ];

        $cachedObjects[] = [
            "name" => "SMTP Configuration",
            "key" => SMTPConfig::CACHE_KEY,
            "size" => null,
            "cachedOn" => null,
            "expiresIn" => null,
            "age" => null
        ];

        if ($this->cache) {
            $timeUnits = new TimeUnits();

            // Check age
            for ($i = 0; $i < count($cachedObjects); $i++) {
                try {
                    $cachedItem = $this->cache->get($cachedObjects[$i]["key"], true);
                } catch (CacheException $e) {
                }

                if (isset($cachedItem)) {
                    $ageDiff = Time::difference($cachedItem->timeStamp);
                    $cachedObjects[$i]["size"] = $cachedItem->size;
                    $cachedObjects[$i]["cachedOn"] = $cachedItem->timeStamp;
                    $cachedObjects[$i]["age"] = $timeUnits->timeToString($ageDiff);

                    if ($cachedItem->ttl) {
                        $expiresIn = $cachedItem->timeStamp + $cachedItem->ttl;
                        $expiresIn = $expiresIn - time();
                        if ($expiresIn > 0) {
                            $cachedObjects[$i]["expiresIn"] = $timeUnits->timeToString($expiresIn);
                        }
                    }
                }
            }
        }

        return $cachedObjects;
    }

    /**
     * @throws AppControllerException
     */
    public function deleteCached(): void
    {
        if (!$this->cache) {
            throw new AppControllerException('Not connected to Cache engine');
        }

        try {
            $key = $this->input()->get("key");
            if (!$key) {
                throw new AppControllerException('Cached item key is required');
            } elseif (!preg_match('/^[\w\-.]+$/', $key)) {
                throw new AppControllerException('Invalid cached item key');
            }
        } catch (AppControllerException $e) {
            $e->setParam("key");
            throw $e;
        }

        try {
            $this->cache->delete($key);
        } catch (\Exception $e) {
        }

        $this->response()->set("status", true);
        $this->messages()->info('Cached item has been removed');
    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Cache Engine')->index(310, 10)
            ->prop("icon", "mdi mdi-memory");

        $this->breadcrumbs("Application", null, "mdi mdi-server-network");

        $this->page()->js($this->request()->url()->root(getenv("ADMIN_TEMPLATE") . '/js/app/caching.min.js'));

        $cacheConfig = $this->app->config()->cache();
        if ($cacheConfig) {
            $cacheConfig = [
                "engine" => $cacheConfig->engine(),
                "host" => $cacheConfig->host(),
                "port" => $cacheConfig->port(),
                "timeOut" => $cacheConfig->timeOut(),
            ];
        }

        $template = $this->template("app/caching.knit")
            ->assign("cacheStatus", $this->cache->isConnected())
            ->assign("cacheConfig", is_array($cacheConfig) ? $cacheConfig : null)
            ->assign("cachedItems", $this->cachedObjects());
        $this->body($template);
    }
}
