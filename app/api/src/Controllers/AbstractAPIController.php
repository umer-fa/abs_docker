<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\APIService;
use App\Common\API\Query;
use App\Common\API\QueryPayload;
use App\Common\Config\APIServerAccess;
use App\Common\Database\API\QueriesPayload;
use App\Common\Exception\API_Exception;
use App\Common\Kernel;
use App\Common\Kernel\Http\Controllers\API_Controller;
use App\Common\Validator;
use Comely\Database\Database;
use Comely\Database\Schema;

/**
 * Class AbstractAPIController
 * @package App\API\Controllers
 */
abstract class AbstractAPIController extends API_Controller
{
    protected APIServerAccess $apiAccess;
    /** @var string */
    protected string $ipAddress;
    /** @var null|Query */
    protected ?Query $queryLog = null;

    /**
     * @throws API_Exception
     */
    public function callback(): void
    {
        $this->app = APIService::getInstance();

        try {
            $this->apiAccess = APIServerAccess::getInstance();
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new API_Exception('PLATFORM_ACCESS_ERROR');
        }

        parent::callback();
    }

    /**
     * @throws API_Exception
     */
    public function onLoad(): void
    {
        // Has a valid remote IP address?
        $this->ipAddress = $this->app->http()->remote()->ipAddress ?? "";
        if (!Validator::isValidIP($this->ipAddress)) {
            throw new API_Exception('BAD_REMOTE_ADDR');
        }

        // Global Status
        if (!$this->apiAccess->globalStatus) {
            throw new API_Exception('API_DISABLED');
        }

        // Schema Events
        Schema::Events()->on_ORM_ModelQueryFail()->listen(function (\Comely\Database\Queries\Query $query) {
            $k = Kernel::getInstance();
            if ($query->error()) {
                $k->errors()->triggerIfDebug(
                    sprintf('[SQL[%s]][%s] %s', $query->error()->sqlState, $query->error()->code, $query->error()->info),
                    E_USER_WARNING
                );
            }
        });

        // Log query
        try {
            $apiLogsDb = $this->app->db()->apiLogs();
            Schema::Bind($apiLogsDb, 'App\Common\Database\API\Queries');

            $this->queryLog = new Query();
            $this->queryLog->id = 0;
            $this->queryLog->set("checksum", "tba");
            $this->queryLog->ipAddress = $this->ipAddress;
            $this->queryLog->method = strtolower($this->request()->method());
            $this->queryLog->endpoint = $this->request()->url()->full();
            $this->queryLog->startOn = microtime(true);
            $this->queryLog->endOn = doubleval(0);
            $this->queryLog->query()->insert();
            $this->queryLog->id = $apiLogsDb->lastInsertId();
            $this->queryLog->set("checksum", $this->queryLog->checksum()->raw());
            $this->queryLog->query()->where("id", $this->queryLog->id)->update();
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new API_Exception('Failed to create API query log');
        }

        // Enable output buffer
        $controller = $this;
        if (!ob_start()) {
            throw new API_Exception('Failed to initialize output buffer');
        }

        register_shutdown_function(function () use ($controller, $apiLogsDb) {
            $buffered = ob_get_contents();
            if (!$buffered) {
                $buffered = null;
            }

            ob_end_clean();

            try {
                $app = APIService::getInstance();
                $queryLog = $controller->queryLog();
                if ($queryLog) {
                    $queryLog->resCode = $controller->response()->code;
                    $queryLog->endOn = microtime(true);
                    $queryPayload = new QueryPayload($queryLog, $controller, $buffered);
                    $encryptedPayload = $app->ciphers()->secondary()->encrypt($queryPayload);
                    $queryLog->resLen = $encryptedPayload->size()->bytes();
                    $queryLog->set("checksum", $queryLog->checksum()->raw());
                    $this->queryLog->query()->where("id", $this->queryLog->id)->update();

                    $apiLogsDb->query()->table(QueriesPayload::NAME)->insert([
                        "query" => $queryLog->id,
                        "encrypted" => $encryptedPayload->raw()
                    ]);
                }
            } catch (\Exception $e) {
                // Write the log
                if (isset($queryLog)) {
                    $data[] = Kernel\ErrorHandler\Errors::Exception2String($e);
                    $data[] = $e->getTraceAsString();
                    $data[] = var_export($this->app->errors()->all(), true);
                    $data[] = "";

                    $this->app->dirs()->log()->dir('queries', true)->write(
                        sprintf('%s', dechex($queryLog->id)),
                        implode("\n\n", $data)
                    );
                }

                $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            }

            print $buffered;
        });

        // API callback
        $this->apiCallback();
    }

    /**
     * @return Query|null
     */
    public function queryLog(): ?Query
    {
        return $this->queryLog;
    }

    /**
     * @return Database
     * @throws API_Exception
     */
    public function apiLogsDb(): Database
    {
        try {
            return $this->app->db()->apiLogs();
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new API_Exception('DB_CONNECTION_ERROR');
        }
    }

    /**
     * @return void
     */
    abstract public function apiCallback(): void;

    /**
     * @return void
     */
    public function onFinish(): void
    {
    }
}
