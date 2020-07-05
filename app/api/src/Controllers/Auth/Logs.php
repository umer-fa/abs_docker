<?php
declare(strict_types=1);

namespace App\API\Controllers\Auth;

use App\Common\Exception\AppException;
use App\Common\Users\Log;
use App\Common\Validator;

/**
 * Class Logs
 * @package App\API\Controllers\Auth
 */
class Logs extends AbstractAuthSessAPIController
{
    public function authSessCallback(): void
    {
    }

    /**
     * @throws AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function get(): void
    {
        // Per Page
        $perPage = (int)trim(strval($this->input()->get("perPage")));
        if (!$perPage) {
            $perPage = 50;
        }

        if (!in_array($perPage, [50, 100, 250])) {
            throw new AppException('Invalid pagination perPage value');
        }

        // Start
        $page = Validator::UInt($this->input()->get("page")) ?? 1;
        $start = ($page * $perPage) - $perPage;

        // Result
        $result = [
            "totalRows" => 0,
            "page" => null,
            "rows" => null,
            "nav" => null,
        ];

        $db = $this->app->db()->primary();

        try {
            $search = $db->query()->table(\App\Common\Database\Primary\Users\Logs::NAME)
                ->where('`user`=?', [$this->authUser->id])
                ->desc("id")
                ->start($start)
                ->limit($perPage)
                ->paginate();

            $result["totalRows"] = $search->totalRows();
            $result["page"] = $page;
            $result["nav"] = $search->compactNav();
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to retrieve activity log');
        }

        $logs = [];
        foreach ($search->rows() as $row) {
            try {
                $log = new Log($row);
                $logs[] = $log;
            } catch (\Exception $e) {
                $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
                continue;
            }
        }

        $result["rows"] = $logs;

        $this->status(true);
        $this->response()->set("logs", $result);
    }
}
