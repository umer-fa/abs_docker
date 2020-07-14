<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Api;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\API\API_Session;
use App\Common\Database\Primary\Users;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Kernel\KnitModifiers;
use App\Common\Validator;
use Comely\Database\Exception\DatabaseException;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;
use Comely\DataTypes\Integers;

/**
 * Class Sessions
 * @package App\Admin\Controllers\Api
 */
class Sessions extends AbstractAdminController
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
        $apiDb = $this->app->db()->apiLogs();
        Schema::Bind($apiDb, 'App\Common\Database\API\Sessions');
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postArchive(): void
    {
        $this->verifyXSRF();

        // Session ID
        $sessionId = Validator::UInt(trim(strval($this->input()->get("sessionId"))));
        try {
            if (!$sessionId) {
                throw new AppControllerException('Session ID is required');
            }

            try {
                /** @var API_Session $session */
                $session = \App\Common\Database\API\Sessions::Find(["id" => $sessionId])->first();
            } catch (ORM_ModelNotFoundException $e) {
                throw new AppControllerException('No such API session exists');
            } catch (DatabaseException $e) {
                $this->app->errors()->trigger($e, E_USER_WARNING);
                throw new AppControllerException('Failed to retrieve API session');
            }

            $session->validate(); // Validate checksum
            if ($session->archived === 1) {
                throw new AppControllerException('This session is already archived');
            }
        } catch (AppException $e) {
            $e->setParam("sessionId");
            throw $e;
        }

        // Verify TOTP
        $this->verifyTotp(trim(strval($this->input()->get("totp"))));

        // Archive Session
        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            // Archive session
            $session->archived = 1;
            $session->query()->update();

            // Delete baggage
            $flush = $session->baggage()->flush();

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to archive session row');
        }

        $this->response()->set("status", true);
        $this->response()->set("refresh", true);
        $this->messages()->success("API session has been archived");
        if ($flush) {
            $this->messages()->info(sprintf('%d session baggage items flushed', $flush));
        }
        $this->messages()->info("Refreshing...");
    }

    /**
     * @throws AppException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('API Sessions')->index(210, 20)
            ->prop("containerIsFluid", true)
            ->prop("icon", "mdi mdi-cookie");

        $this->page()->js($this->request()->url()->root(getenv("ADMIN_TEMPLATE") . '/js/app/api_sessions.min.js'));
        $this->breadcrumbs("API Server", null, "mdi mdi-api");

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
            "archived" => null,
            "type" => null,
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
                if (!in_array($key, ["token", "ip_address", "auth_user"])) {
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

            // Device type & Is archived
            $tokenType = $this->input()->get("type");
            if ($tokenType && is_string($tokenType)) {
                $tokenType = strtolower($tokenType);
                if (!in_array($tokenType, ["web", "mobile", "desktop"])) {
                    throw new AppException('Invalid token device type to search for');
                }

                $search["type"] = $tokenType;
                $search["advanced"] = true;
            }

            $isArchived = $this->input()->get("archived");
            if ($isArchived && is_string($isArchived)) {
                $isArchived = strtolower($isArchived);
                if (!in_array($isArchived, ["yes", "no"])) {
                    throw new AppException('Invalid token device type to search for');
                }

                $search["archived"] = $isArchived;
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
            $sessQuery = $apiLogsDb->query()->table(\App\Common\Database\API\Sessions::NAME)
                ->limit($search["perPage"])
                ->start($start);

            if ($search["sort"] === "asc") {
                $sessQuery->asc("id");
            } else {
                $sessQuery->desc("id");
            }

            $whereQuery = "`id`>0";
            $whereData = [];

            // Search key/value
            if (isset($search["key"], $search["value"])) {
                $searchValue = $search["value"];
                if ($search["key"] === "auth_user") {
                    $user = Users::Email($searchValue);
                    $whereQuery .= ' AND `auth_user_id`=?';
                    $whereData[] = $user->id;
                } else {
                    if ($search["key"] === "token") {
                        $searchValue = hex2bin($searchValue);
                    }

                    $whereQuery .= sprintf(' AND `%s` LIKE ?', $search["key"]);
                    $whereData[] = sprintf('%%%s%%', $searchValue);
                }
            }

            // Device type & Is archived
            if (isset($search["type"])) {
                $whereQuery .= ' AND `type`=?';
                $whereData[] = $search["type"];
            }

            if (isset($search["archived"])) {
                $whereQuery .= ' AND `archived`=?';
                $whereData[] = $search["archived"] === "yes" ? 1 : 0;
            }

            $sessQuery->where($whereQuery, $whereData);
            $sessions = $sessQuery->paginate();

            $result["page"] = $page;
            $result["count"] = $sessions->totalRows();
            $result["rows"] = [];
            $result["nav"] = $sessions->compactNav();
            $result["status"] = true;
        } catch (AppException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('An error occurred while searching API queries');
        }

        // Work with data...
        if (isset($sessions) && $sessions->count()) {
            foreach ($sessions->rows() as $sessRow) {
                try {
                    $sess = new API_Session($sessRow);

                    try {
                        $sess->validate();
                    } catch (AppException $e) {
                    }

                    if ($sess->authUserId) {
                        $sessAuthUser = Users::get($sess->authUserId);
                    }

                    $sessObj = Validator::JSON_Filter($sess, "api.Session");
                    $sessObj["token"] = $sess->token()->hexits(false);
                    $sessObj["authUsername"] = isset($sessAuthUser) && $sessAuthUser ? $sessAuthUser->username : null;

                    $result["rows"][] = $sessObj;
                    unset($sess, $sessAuthUser, $sessObj);
                } catch (\Exception $e) {
                    $this->app->errors()->trigger($e, E_USER_WARNING);
                }
            }
        }

        // Search Link
        $search["link"] = $this->authRoot . sprintf(
                'api/sessions?key=%s&value=%s&type=%s&archived=%s&sort=%s&perPage=%d',
                $search["key"],
                $search["value"],
                $search["type"],
                $search["archived"],
                $search["sort"],
                $search["perPage"]
            );

        // Knit Modifiers
        KnitModifiers::Dated($this->knit());
        KnitModifiers::Hex($this->knit());

        $template = $this->template("api/sessions.knit")
            ->assign("errorMessage", $errorMessage)
            ->assign("search", $search)
            ->assign("result", $result)
            ->assign("perPageOpts", self::PER_PAGE_OPTIONS);
        $this->body($template);
    }
}
