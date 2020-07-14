<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Staff;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Admin\Administrator;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use App\Common\Kernel\KnitModifiers;
use App\Common\Validator;
use Comely\Database\Exception\DatabaseException;
use Comely\DataTypes\Integers;

/**
 * Class Log
 * @package App\Admin\Controllers\Staff
 */
class Log extends AbstractAdminController
{
    private const PER_PAGE_OPTIONS = [50, 100, 250];
    private const PER_PAGE_DEFAULT = self::PER_PAGE_OPTIONS[1];

    /** @var array */
    private array $adminsList;
    /** @var bool */
    private bool $listingAllAdmins;

    /**
     * @throws AppException
     */
    public function adminCallback(): void
    {
        // Self
        $this->adminsList = [
            [
                "id" => $this->authAdmin->id,
                "email" => $this->authAdmin->email
            ]
        ];

        // Privileged?
        $this->listingAllAdmins = false;
        if ($this->authAdmin->privileges()->root()) {
            $this->listingAllAdmins = true;
        } elseif ($this->authAdmin->privileges()->viewAdminsLogs) {
            $this->listingAllAdmins = true;
        }

        if ($this->listingAllAdmins) {
            $canIncludeRootAdmins = false;
            if ($this->authAdmin->privileges()->root()) {
                $canIncludeRootAdmins = true;
            }

            try {
                // Get admins list
                $admins = Administrators::Find()->query("WHERE 1 ORDER BY `email` ASC", [])->all();
                $adminsList = [];
                /** @var Administrator $admin */
                foreach ($admins as $admin) {
                    try {
                        if ($admin->privileges()->root()) {
                            if (!$canIncludeRootAdmins) {
                                continue;
                            }
                        }
                    } catch (AppException $e) {
                        $this->app->errors()->trigger($e->getMessage(), E_USER_WARNING);
                    }

                    $adminsList[] = [
                        "id" => $admin->id,
                        "email" => $admin->email
                    ];
                }
            } catch (DatabaseException $e) {
                $this->app->errors()->trigger($e, E_USER_WARNING);
            }

            if (isset($adminsList) && $adminsList) {
                $this->adminsList = $adminsList;
            }
        }
    }

    /**
     * @param $id
     * @return string|null
     */
    private function adminIdToEmail($id): ?string
    {
        $id = Validator::UInt($id);
        if (is_int($id)) {
            foreach ($this->adminsList as $admin) {
                if ($admin["id"] === $id) {
                    return $admin["email"];
                }
            }
        }

        return null;
    }

    /**
     * @throws AppException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Audit Log')->index(200, 20)
            ->prop("containerIsFluid", true)
            ->prop("icon", "mdi mdi-book-open");

        $this->breadcrumbs("Staff Management", null, "mdi mdi-shield-account");

        $result = [
            "status" => false,
            "count" => 0,
            "page" => null,
            "rows" => null,
            "nav" => null
        ];

        $search = [
            "admin" => null,
            "match" => null,
            "sort" => "desc",
            "perPage" => self::PER_PAGE_DEFAULT,
            "link" => null
        ];

        // Get Logs
        try {
            $errorMessage = null;
            $searchLogsFor = null;
            $adminId = Validator::UInt($this->input()->get("admin"));
            if ($adminId) {
                $searchLogsFor = $adminId;
            } else {
                if (!$this->listingAllAdmins) {
                    $searchLogsFor = $this->authAdmin->id;
                }
            }

            // Can search this admin?
            if ($searchLogsFor) {
                $canSearch = false;
                foreach ($this->adminsList as $admin) {
                    if ($admin["id"] === $searchLogsFor) {
                        $canSearch = true;
                        break;
                    }
                }

                if (!$canSearch) {
                    throw new AppException('Log audit for requested administrator is not permitted');
                }
            }

            $search["admin"] = $searchLogsFor;

            // Match Query
            $match = $this->input()->get("match");
            if ($match && is_string($match)) {
                $match = trim($match);
                $search["match"] = $match;

                $matchLen = mb_strlen($match);
                if (!preg_match('/^[\w\s\-@+=:;#.]+$/', $match)) {
                    throw new AppException('Search query contains an illegal character');
                } elseif (!Integers::Range($matchLen, 1, 64)) {
                    throw new AppException('Search query exceeds min/max length');
                }
            }

            // Sort By
            $sort = $this->input()->get("sort");
            if (is_string($sort) && in_array(strtolower($sort), ["asc", "desc"])) {
                $search["sort"] = $sort;
            }

            // Per Page
            $perPage = Validator::UInt($this->input()->get("perPage"));
            if ($perPage) {
                if (!in_array($perPage, self::PER_PAGE_OPTIONS)) {
                    throw new AppException('Invalid search results per page count');
                }
            }

            $search["perPage"] = is_int($perPage) && $perPage > 0 ? $perPage : self::PER_PAGE_DEFAULT;

            $page = Validator::UInt($this->input()->get("page")) ?? 1;
            $start = ($page * $perPage) - $perPage;

            $db = $this->app->db()->primary();
            $logsQuery = $db->query()->table(Administrators\Logs::NAME)
                ->limit($search["perPage"])
                ->start($start);

            if ($search["sort"] === "asc") {
                $logsQuery->asc("time_stamp");
            } else {
                $logsQuery->desc("time_stamp");
            }

            $whereQuery = "`id`>0";
            $whereData = [];

            if (is_int($search["admin"])) {
                $whereQuery .= ' AND `admin`=?';
                $whereData[] = $search["admin"];
            }

            if ($search["match"]) {
                $whereQuery .= ' AND (`flags` LIKE ? OR `log` LIKE ? OR `ip_address` LIKE ?)';
                $whereData[] = sprintf('%%%s%%', $search["match"]);
                $whereData[] = sprintf('%%%s%%', $search["match"]);
                $whereData[] = sprintf('%%%s%%', $search["match"]);
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
            throw new AppException('An error occurred while searching logs');
        }

        // Append Admin Emails to Results
        if ($result["status"]) {
            if (is_array($result["rows"])) {
                for ($i = 0; $i < count($result["rows"]); $i++) {
                    $result["rows"][$i]["adminEmail"] = $this->adminIdToEmail($result["rows"][$i]["admin"]);
                }
            }
        }

        // Search Link
        $search["link"] = $this->authRoot . sprintf(
                'staff/log?admin=%d&match=%s&sort=%s&perPage=%s',
                $search["admin"],
                $search["match"],
                $search["sort"],
                $search["perPage"]
            );

        // Knit Modifiers
        KnitModifiers::Dated($this->knit());

        $template = $this->template("staff/log.knit")
            ->assign("errorMessage", $errorMessage)
            ->assign("search", $search)
            ->assign("result", $result)
            ->assign("perPageOpts", self::PER_PAGE_OPTIONS)
            ->assign("listingAllAdmins", $this->listingAllAdmins)
            ->assign("adminsList", $this->adminsList);
        $this->body($template);
    }
}
