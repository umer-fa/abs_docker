<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Api;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\API\Query;
use App\Common\Database\Primary\Users;
use App\Common\Exception\AppException;
use App\Common\Kernel\KnitModifiers;
use App\Common\Validator;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;
use Comely\DataTypes\Integers;

/**
 * Class Queries
 * @package App\Admin\Controllers\Api
 */
class Queries extends AbstractAdminController
{
    private const PER_PAGE_OPTIONS = [50, 100, 250, 500];
    private const PER_PAGE_DEFAULT = self::PER_PAGE_OPTIONS[1];

    /**
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function adminCallback(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
    }

    /**
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function getQuery(): void
    {
        $this->verifyXSRF();

        $apiLogsDb = $this->app->db()->apiLogs();
        Schema::Bind($apiLogsDb, 'App\Common\Database\API\Queries');

        // Check admin privileges
        if (!$this->authAdmin->privileges()->root()) {
            if (!$this->authAdmin->privileges()->viewAPIQueriesPayload) {
                throw new AppException('You are not authorized to decrypt API queries');
            }
        }

        // Query hexId
        $queryId = $this->input()->get("id");
        if (!is_string($queryId) || !preg_match('/^[a-f0-9]{1,32}$/i', $queryId)) {
            throw new AppException('Invalid API query rayId');
        }

        // Load query
        try {
            /** @var Query $query */
            $query = \App\Common\Database\API\Queries::Find(["id" => hexdec($queryId)])->first();

            try {
                $query->validateChecksum();
            } catch (AppException $e) {
            }
        } catch (ORM_ModelNotFoundException $e) {
            throw new AppException(sprintf('No API query with Ray Id "%s" was found', $queryId));
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to retrieve API query');
        }

        // Payload
        try {
            $query->payload();
        } catch (AppException $e) {
            trigger_error($e->getMessage(), E_USER_NOTICE);
        }

        // Final touches
        $query->method = strtoupper($query->method);

        $this->response()->set("status", true);
        $this->response()->set("query", $query->array());
    }

    /**
     * @throws AppException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('API Queries')->index(210, 30)
            ->prop("containerIsFluid", true)
            ->prop("icon", "ion ion-ios-cloud-download-outline");

        $this->page()->js($this->request()->url()->root(getenv("ADMIN_TEMPLATE") . '/js/app/api_queries.min.js'));
        $this->breadcrumbs("API Server", null, "ion ion-ios-cloud");

        $result = [
            "status" => false,
            "count" => 0,
            "page" => null,
            "rows" => null,
            "nav" => null
        ];

        $search = [
            "key" => null,
            "value" => null,
            "method" => null,
            "endpoint" => null,
            "sort" => "desc",
            "perPage" => self::PER_PAGE_DEFAULT,
            "advanced" => false,
            "link" => null
        ];

        // Get Logs
        try {
            $errorMessage = null;

            // Match Query
            $key = $this->input()->get("key");
            if ($key && is_string($key)) {
                $key = strtolower(trim($key));
                if (!in_array($key, ["ip_address", "flag_api_sess", "flag_user_id"])) {
                    throw new AppException('Invalid search column name');
                }

                $search["key"] = $key;
                $value = $this->input()->get("value");
                if (!$value || !is_string($value)) {
                    throw new AppException('Search value is required');
                }

                $value = trim($value);
                $valueLen = strlen($value);
                if (!preg_match('/^[\w\s\-@+=:;#.]+$/', $value)) {
                    throw new AppException('Search value  contains an illegal character');
                } elseif (!Integers::Range($valueLen, 1, 64)) {
                    throw new AppException('Search query exceeds min/max length');
                }

                $search["value"] = $value;
            }

            // Method and Endpoint
            $httpMethod = $this->input()->get("method");
            if ($httpMethod && is_string($httpMethod)) {
                $httpMethod = strtolower($httpMethod);
                if (!in_array($httpMethod, ["get", "post", "put", "delete", "options"])) {
                    throw new AppException('Invalid HTTP method to search for');
                }

                $search["method"] = $httpMethod;
                $search["advanced"] = true;
            }

            $endpoint = $this->input()->get("endpoint");
            if ($endpoint && is_string($endpoint)) {
                $endpointLen = strlen($endpoint);
                if (!preg_match('/^[\w\/\-?@#:]+$/', $endpoint)) {
                    throw new AppException('Invalid HTTP endpoint URL');
                } elseif (!Integers::Range($endpointLen, 1, 64)) {
                    throw new AppException('Search for API endpoint exceeds min/max length');
                }

                $search["endpoint"] = $endpoint;
                $search["advanced"] = true;
            }

            // Sort By
            $sort = $this->input()->get("sort");
            if (is_string($sort) && in_array(strtolower($sort), ["asc", "desc"])) {
                $search["sort"] = $sort;
                if ($search["sort"] === "asc") {
                    $search["advanced"] = true;
                }
            }

            // Per Page
            $perPage = Validator::UInt($this->input()->get("perPage"));
            if ($perPage) {
                if (!in_array($perPage, self::PER_PAGE_OPTIONS)) {
                    throw new AppException('Invalid search results per page count');
                }
            }

            $search["perPage"] = is_int($perPage) && $perPage > 0 ? $perPage : self::PER_PAGE_DEFAULT;
            if ($search["perPage"] !== self::PER_PAGE_DEFAULT) {
                $search["advanced"] = true;
            }

            $page = Validator::UInt($this->input()->get("page")) ?? 1;
            $start = ($page * $perPage) - $perPage;

            $apiLogsDb = $this->app->db()->apiLogs();
            $logsQuery = $apiLogsDb->query()->table(\App\Common\Database\API\Queries::NAME)
                ->limit($search["perPage"])
                ->start($start);

            if ($search["sort"] === "asc") {
                $logsQuery->asc("id");
            } else {
                $logsQuery->desc("id");
            }

            $whereQuery = "`id`>0";
            $whereData = [];

            // Search key/value
            if (isset($search["key"], $search["value"])) {
                $searchValue = $search["value"];
                if ($search["key"] === "flag_user_id") {
                    $user = Users::Email($searchValue);
                    $whereQuery .= ' AND `flag_user_id`=?';
                    $whereData[] = $user->id;
                } else {
                    if ($search["key"] === "flag_api_sess") {
                        $searchValue = hex2bin($searchValue);
                    }

                    $whereQuery .= sprintf(' AND `%s` LIKE ?', $search["key"]);
                    $whereData[] = sprintf('%%%s%%', $searchValue);
                }
            }

            // Search Method & Endpoint
            if (isset($search["method"])) {
                $whereQuery .= ' AND `method`=?';
                $whereData[] = $search["method"];
            }

            if (isset($search["endpoint"])) {
                $whereQuery .= ' AND `endpoint` LIKE ?';
                $whereData[] = sprintf('%%%s%%', $search["endpoint"]);
            }

            $logsQuery->where($whereQuery, $whereData);
            $logs = $logsQuery->paginate();

            $result["page"] = $page;
            $result["count"] = $logs->totalRows();
            $result["rows"] = $logs->rows();
            $result["nav"] = $logs->compactNav();
            $result["status"] = true;
        } catch (AppException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('An error occurred while searching API queries');
        }

        // Work with data...
        if ($result["status"]) {
            if (is_array($result["rows"])) {
                for ($i = 0; $i < count($result["rows"]); $i++) {
                    // Remove binary checksum value
                    $result["rows"][$i]["checksum"] = null;
                    if ($result["rows"][$i]["flag_api_sess"]) {
                        $result["rows"][$i]["flag_api_sess"] = bin2hex($result["rows"][$i]["flag_api_sess"]);
                    }

                    // Endpoint Short
                    $shortEndpoint = substr($result["rows"][$i]["endpoint"], 0, 45);
                    if (strlen($result["rows"][$i]["endpoint"]) > 45) {
                        $shortEndpoint .= "...";
                    }

                    $result["rows"][$i]["endpoint_short"] = $shortEndpoint;

                    // Timestamp
                    $result["rows"][$i]["time_stamp"] = intval(explode(".", strval($result["rows"][$i]["start_on"]))[0]);

                    // User registered e-mail
                    $rowFlagUserId = intval($result["rows"][$i]["flag_user_id"]);
                    if (is_int($rowFlagUserId) && $rowFlagUserId > 0) {
                        $rowFlagUser = Users::get($rowFlagUserId);
                        $result["rows"][$i]["flag_user_username"] = $rowFlagUser->username;
                    }

                    unset($shortEndpoint, $rowFlagUser, $rowFlagUserId);
                }
            }
        }

        // Search Link
        $search["link"] = $this->authRoot . sprintf(
                'api/queries?key=%s&value=%s&method=%s&endpoint=%s&sort=%s&perPage=%d',
                $search["key"],
                $search["value"],
                $search["method"],
                $search["endpoint"],
                $search["sort"],
                $search["perPage"]
            );

        // Knit Modifiers
        KnitModifiers::Dated($this->knit());
        KnitModifiers::Hex($this->knit());

        $template = $this->template("api/queries.knit")
            ->assign("errorMessage", $errorMessage)
            ->assign("search", $search)
            ->assign("result", $result)
            ->assign("perPageOpts", self::PER_PAGE_OPTIONS);
        $this->body($template);
    }
}
