<?php
declare(strict_types=1);

namespace App\Admin\Controllers\App;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Exception\AppControllerException;
use App\Common\Kernel\Databases;
use App\Common\Kernel\ErrorHandler\Errors;
use Comely\Database\Exception\DatabaseException;
use Comely\Database\Schema;
use Comely\Filesystem\Directory;

/**
 * Class Dbs
 * @package App\Admin\Controllers\App
 */
class Dbs extends AbstractAdminController
{
    public function adminCallback(): void
    {
    }

    /**
     * @return array
     */
    private function dbStatuses(): array
    {
        $dbStatuses[] = [
            "name" => "Primary",
            "node" => Databases::PRIMARY,
            "status" => false,
            "error" => null,
        ];

        $dbStatuses[] = [
            "name" => "API Logs",
            "node" => Databases::API_LOGS,
            "status" => false,
            "error" => null,
        ];

        for ($i = 0; $i < count($dbStatuses); $i++) {
            try {
                $this->app->db()->get($dbStatuses[$i]["node"]);
                $dbStatuses[$i]["status"] = true;
            } catch (DatabaseException $e) {
                $dbStatuses[$i]["error"] = Errors::Exception2String($e);
            }
        }

        return $dbStatuses;
    }

    /**
     * @param string $dirPath
     * @param string $prefix
     * @return array
     */
    private function scanDirForTables(string $dirPath, string $prefix = 'App\Common\Database'): array
    {
        $tables = [];

        try {
            $dir = new Directory($dirPath);
            $dirFiles = $dir->scan(true, SCANDIR_SORT_ASCENDING);
            foreach ($dirFiles as $file) {
                if (is_dir($file)) {
                    $subDirTables = $this->scanDirForTables($file, sprintf('%s\%s', $prefix, basename($file)));
                    $tables = array_merge($tables, $subDirTables);
                    continue;
                }

                $fileBasename = basename($file);
                if (preg_match('/^\w+\.php$/', $fileBasename)) {
                    if (!preg_match('/^abstract/i', $fileBasename)) {
                        $tables[] = sprintf('%s\%s', $prefix, substr($fileBasename, 0, -4));
                    }
                }
            }
        } catch (\Exception $e) {
            trigger_error(sprintf('Failed to scan directory "%s" for DB tables', $dirPath), E_USER_WARNING);
            Errors::Exception2Error($e);
        }

        return $tables;
    }

    /**
     * @return array
     */
    private function dbTables(): array
    {
        return $this->scanDirForTables($this->app->dirs()->root()->suffix('common/Database'));
    }

    /**
     * @throws AppControllerException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function getDbTableMigration(): void
    {
        // Table
        try {
            $table = $this->input()->get("table");
            if (!$table) {
                throw new AppControllerException('Select a table for DB migration');
            }

            if (!class_exists($table)) {
                throw new AppControllerException('DB table class not found');
            } elseif (!is_subclass_of($table, 'App\Common\Database\AbstractAppTable', true)) {
                throw new AppControllerException('DB table class is not an AppTable');
            }
        } catch (AppControllerException $e) {
            $e->setParam("table");
            throw $e;
        }

        // Determine Database
        $dbTag = "primary";
        if (preg_match('/^App\\\Common\Database\\\API/i', $table)) {
            $dbTag = "api_logs";
        }

        // Get Database
        $db = $this->app->db()->get($dbTag);

        // Bind Table and DB instance
        Schema::Bind($db, $table);

        // Get Migration
        $boundDbTable = Schema::Table($table);
        $migration = Schema::Migration($table)->createIfNotExists()->createTable();

        $this->response()->set("status", true);
        $this->response()->set("table", $boundDbTable->table()->name);
        $this->response()->set("migration", $migration);
    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Db Schematics')->index(310, 20)
            ->prop("icon", "mdi mdi-database");

        $this->page()->js($this->request()->url()->root(getenv("ADMIN_TEMPLATE") . '/js/app/dbs.min.js'));

        $this->breadcrumbs("Application", null, "mdi mdi-server-network");

        // Database Statuses
        $dbStatuses = $this->dbStatuses();

        // Database Tables
        $dbTables = $this->dbTables();

        $template = $this->template("app/dbs.knit")
            ->assign("dbTables", $dbTables)
            ->assign("dbStatuses", $dbStatuses);
        $this->body($template);
    }
}
