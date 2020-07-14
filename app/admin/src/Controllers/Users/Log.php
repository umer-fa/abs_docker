<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Users;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Database\Primary\Users;
use App\Common\Database\Primary\Users\Logs;
use App\Common\Exception\AppException;
use App\Common\Kernel\KnitModifiers;
use App\Common\Users\User;
use App\Common\Validator;
use Comely\Database\Schema;
use Comely\DataTypes\Integers;

/**
 * Class Log
 * @package App\Admin\Controllers\Users
 */
class Log extends AbstractAdminController
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
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Logs');
    }

    /**
     * @throws AppException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Activity Log')->index(1100, 40)
            ->prop("containerIsFluid", true)
            ->prop("icon", "mdi mdi-account-search");

        $this->breadcrumbs("Users Control", null, "ion ion-ios-people");

        $errorMessage = null;

        $result = [
            "status" => false,
            "count" => 0,
            "page" => null,
            "rows" => null,
            "nav" => null
        ];

        $search = [
            "user" => null,
            "match" => null,
            "sort" => "desc",
            "perPage" => self::PER_PAGE_DEFAULT,
            "advanced" => false,
            "link" => null
        ];

        try {
            // User
            $userId = trim(strval($this->input()->get("user")));
            if ($userId) {
                $userMatchCol = strpos($userId, "@") ? "Email" : "Username";
                /** @var User $user */
                $user = call_user_func_array(
                    ['App\Common\Database\Primary\Users', $userMatchCol],
                    [$userId, true]
                );

                $search["user"] = $userId;
            }

            // Match
            $match = trim(strval($this->input()->get("match")));
            if ($match) {
                if (!preg_match('/^[\w\s\-@+=:;#.]+$/', $match)) {
                    throw new AppException('Search query contains an illegal character');
                } elseif (!Integers::Range(strlen($match), 1, 64)) {
                    throw new AppException('Search query exceeds min/max length');
                }

                $search["match"] = $match;
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

            $db = $this->app->db()->primary();
            $logsQuery = $db->query()->table(Logs::NAME)
                ->limit($search["perPage"])
                ->start($start);

            if ($search["sort"] === "asc") {
                $logsQuery->asc("id");
            } else {
                $logsQuery->desc("id");
            }

            $whereQuery = "`id`>0";
            $whereData = [];

            // User
            if (isset($user)) {
                $whereQuery .= ' AND `user`=?';
                $whereData[] = $user->id;
            }

            // Match
            if (isset($search["match"])) {
                $whereQuery .= ' AND (`flags` LIKE ? OR `log` LIKE ?)';
                $whereData[] = sprintf('%%%s%%', $search["match"]);
                $whereData[] = sprintf('%%%s%%', $search["match"]);
            }

            $logsQuery->where($whereQuery, $whereData);
            $logs = $logsQuery->paginate();

            $result["page"] = $page;
            $result["count"] = $logs->totalRows();
            $result["nav"] = $logs->compactNav();
            $result["rows"] = [];
            $result["status"] = true;
        } catch (AppException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
            $errorMessage = "An error occurred while searching activity log";
        }

        if (isset($logs) && $logs->count()) {
            foreach ($logs->rows() as $logRow) {
                unset($log, $logUser);

                try {
                    $log = new \App\Common\Users\Log($logRow);
                    $logUser = Users::get($log->user);
                    $log = Validator::JSON_Filter($log, sprintf("log:%d", $log->id));
                    $log["data"] = $log["data"] ? json_encode($log["data"]) : null;
                    $log["username"] = $logUser->username;
                    $result["rows"][] = $log;
                } catch (\Exception $e) {
                    $this->app->errors()->trigger($e, E_USER_WARNING);
                }
            }
        }

        // Search Link
        $search["link"] = $this->authRoot . sprintf(
                'users/log?user=%s&match=%s&sort=%s&perPage=%d',
                $search["user"],
                $search["match"],
                $search["sort"],
                $search["perPage"]
            );

        // Knit Modifiers
        KnitModifiers::Dated($this->knit());
        KnitModifiers::Hex($this->knit());

        $template = $this->template("users/log.knit")
            ->assign("errorMessage", $errorMessage)
            ->assign("search", $search)
            ->assign("result", $result)
            ->assign("perPageOpts", self::PER_PAGE_OPTIONS);
        $this->body($template);
    }
}
