<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

use App\Admin\AppAdmin;
use App\Common\Exception\AppException;
use App\Common\Kernel\Http\Controllers\GenericHttpController;
use Comely\Database\Queries\Query;
use Comely\Database\Schema;
use Comely\Filesystem\Exception\FilesystemException;
use Comely\Knit\Knit;

/**
 * Class AbstractAdminController
 * @package App\Admin\Controllers
 */
abstract class AbstractAdminController extends GenericHttpController
{
    /** @var null */
    //protected $authAdmin = null;
    /** @var null|string */
    protected ?string $authToken = null;
    /** @var null|string */
    protected ?string $authRoot = null;
    /** @var array|null */
    private ?array $breadcrumbs = null;

    /**
     * @throws \Exception
     */
    public function callback(): void
    {
        $this->app = AppAdmin::getInstance();
        parent::callback();
    }

    /**
     * @throws AppException
     * @throws \App\Common\Exception\AppConfigException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Sessions\Exception\ComelySessionException
     */
    public function onLoad(): void
    {
        // Configure HTTP Cookies Domain
        $this->app->http()->cookies()->domain($this->app->config()->adminHost());

        // Instantiate Session
        $this->initSession();

        // ORM Tables
        $db = $this->app->db()->primary();

        // Schema Events
        Schema::Events()->on_ORM_ModelQueryFail()->listen(function (Query $query) {
            $app = AppAdmin::getInstance();
            if ($query->error()) {
                $app->errors()->triggerIfDebug(
                    sprintf('[SQL[%s]][%s] %s', $query->error()->sqlState, $query->error()->code, $query->error()->info),
                    E_USER_WARNING
                );
            }
        });

        // Authentication?
        $authenticate = true;
        if (get_called_class() === 'App\Admin\Controllers\Login') {
            $authenticate = false;
        }

        if ($authenticate) {
            $this->authenticate();
        }

        // Administration Panel callback
        $this->adminCallback();
    }

    /**
     * @throws AppException
     */
    private function authenticate(): void
    {
        throw new AppException('Not authenticated');
    }

    /**
     * @return Knit
     * @throws AppException
     */
    public function knit(): Knit
    {
        $knit = parent::knit();
        $currentTemplate = trim(strval(getenv("COMELY_APP_ADMIN_TMPL")));
        if (!$currentTemplate) {
            $currentTemplate = "default";
        }

        try {
            var_dump($this->app->dirs()->root()->path());
            $templateDir = $this->app->dirs()->root()->dir("templates", false)
                ->dir($currentTemplate, false);
        } catch (FilesystemException $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Cannot load template directory');
        }

        $knit->dirs()->templates($templateDir);
        return $knit;
    }

    /**
     * @return void
     */
    abstract public function adminCallback(): void;

    /**
     * @return void
     */
    public function onFinish(): void
    {
    }
}
