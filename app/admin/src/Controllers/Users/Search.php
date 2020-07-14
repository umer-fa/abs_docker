<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Users;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Database\Primary\Countries;
use App\Common\Database\Primary\Users;
use App\Common\Exception\AppException;
use App\Common\Kernel\KnitModifiers;
use App\Common\Users\User;
use App\Common\Validator;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;
use Comely\DataTypes\Integers;

/**
 * Class Search
 * @package App\Admin\Controllers\Users
 */
class Search extends AbstractAdminController
{
    private const PER_PAGE_OPTIONS = [50, 100, 250, 500];
    private const PER_PAGE_DEFAULT = self::PER_PAGE_OPTIONS[1];

    /**
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function adminCallback(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Countries');
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
    }

    /**
     * @throws AppException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Search Users')->index(1100, 10)
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
            "key" => null,
            "value" => null,
            "status" => null,
            "country" => null,
            "sort" => "desc",
            "perPage" => self::PER_PAGE_DEFAULT,
            "advanced" => false,
            "link" => null
        ];

        try {
            // Match Query
            $key = $this->input()->get("key");
            if ($key && is_string($key)) {
                $key = strtolower(trim($key));
                if (!in_array($key, ["referrer", "name", "username", "email", "phone_sms", "ip_addr"])) {
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

            // Status
            $status = $this->input()->get("status");
            if ($status && is_string($status)) {
                $status = strtolower(trim($status));
                if (!in_array($status, ["active", "frozen", "disabled"])) {
                    throw new AppException('Invalid search users status');
                }

                $search["status"] = $status;
            }

            // Country
            $country = $this->input()->get("country");
            if ($country && is_string($country)) {
                if (strlen($country) !== 3) {
                    throw new AppException('Invalid country selected');
                }

                try {
                    $country = Countries::get($country);
                } catch (\Exception $e) {
                    $this->app->errors()->trigger($e, E_USER_WARNING);
                }

                if (isset($country)) {
                    $search["country"] = $country->code;
                }
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
            $usersQuery = $db->query()->table(Users::NAME)
                ->limit($search["perPage"])
                ->start($start);

            if ($search["sort"] === "asc") {
                $usersQuery->asc("id");
            } else {
                $usersQuery->desc("id");
            }

            $whereQuery = "`id`>0";
            $whereData = [];

            // Search key/value
            if (isset($search["key"], $search["value"])) {
                $searchValue = $search["value"];
                switch ($search["key"]) {
                    case "referrer":
                        try {
                            /** @var User $referrer */
                            $referrer = Users::Find()->query(
                                'WHERE `username` LIKE ? OR `email` LIKE ?',
                                ["%" . $searchValue . "%", "%" . $searchValue . "%"]
                            )->first();
                        } catch (ORM_ModelNotFoundException $e) {
                        } catch (\Exception $e) {
                            $this->app->errors()->trigger($e, E_USER_WARNING);
                        }

                        $whereQuery .= ' AND `referrer`=?';
                        $whereData[] = isset($referrer) ? $referrer->id : 0;
                        break;
                    case "name":
                        $whereQuery .= ' AND (`first_name` LIKE ? OR `last_name` LIKE ? )';
                        $whereData[] = sprintf('%%%s%%', $searchValue);
                        $whereData[] = sprintf('%%%s%%', $searchValue);
                        break;
                    case "email":
                    case "phone_sms":
                    case "username":
                        $whereQuery .= sprintf(' AND `%s` LIKE ?', $search["key"]);
                        $whereData[] = sprintf('%%%s%%', $searchValue);
                        break;
                    case "ip_addr":
                        $logsQuery = $db->fetch(
                            sprintf('SELECT `user` FROM `%s` WHERE `ip_address` LIKE ? GROUP BY `user`', Users\Logs::NAME),
                            ["%" . $searchValue . "%"]
                        )->all();
                        $logsUsersId = [0];
                        if ($logsQuery) {
                            $logsUsersId = [];
                            foreach ($logsQuery as $logIPUser) {
                                $logsUsersId[] = $logIPUser["user"];
                            }
                        }

                        $whereQuery .= sprintf(' AND `id` IN (%s)', implode(",", $logsUsersId));
                        break;
                }
            }

            // Search Country & Status
            if (isset($search["status"])) {
                $whereQuery .= ' AND `status`=?';
                $whereData[] = $search["status"];
                $search["advanced"] = true;
            }

            if (isset($search["country"])) {
                $whereQuery .= ' AND `country`=?';
                $whereData[] = $search["country"];
                $search["advanced"] = true;
            }

            $usersQuery->where($whereQuery, $whereData);
            $users = $usersQuery->paginate();

            $result["page"] = $page;
            $result["count"] = $users->totalRows();
            $result["nav"] = $users->compactNav();
            $result["status"] = true;
        } catch (AppException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
            $errorMessage = "An error occurred while searching users";
        }

        if (isset($users) && $users->count()) {
            foreach ($users->rows() as $userRow) {
                try {
                    $user = new User($userRow);
                    try {
                        $user->validate();
                    } catch (AppException $e) {
                    }

                    $result["rows"][] = $user;
                } catch (\Exception $e) {
                    $this->app->errors()->trigger($e, E_USER_WARNING);
                }
            }
        }

        // Search Link
        $search["link"] = $this->authRoot . sprintf(
                'users/search?key=%s&value=%s&status=%s&country=%s&sort=%s&perPage=%d',
                $search["key"],
                $search["value"],
                $search["status"],
                $search["country"],
                $search["sort"],
                $search["perPage"]
            );

        // Knit Modifiers
        KnitModifiers::Dated($this->knit());
        KnitModifiers::Hex($this->knit());

        $template = $this->template("users/search.knit")
            ->assign("errorMessage", $errorMessage)
            ->assign("search", $search)
            ->assign("result", $result)
            ->assign("countries", Countries::List())
            ->assign("perPageOpts", self::PER_PAGE_OPTIONS);
        $this->body($template);
    }
}
