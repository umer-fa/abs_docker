<?php
declare(strict_types=1);

namespace App\Common\API;

use App\Common\Kernel;
use App\Common\Kernel\Http\Controllers\API_Controller;

/**
 * Class QueryPayload
 * @package App\Common\API
 */
class QueryPayload
{
    /** @var int */
    private int $query;
    /** @var array */
    private array $reqHeaders;
    /** @var array */
    private array $resHeaders;
    /** @var array */
    private array $reqBody;
    /** @var null|string */
    private ?string $resBody;
    /** @var array */
    private array $dbQueries;
    /** @var array */
    private array $errors;

    /**
     * QueryPayload constructor.
     * @param Query $query
     * @param API_Controller $controller
     * @param string|null $body
     */
    public function __construct(Query $query, API_Controller $controller, ?string $body)
    {
        $k = Kernel::getInstance();

        $this->query = $query->id;
        $this->reqHeaders = $controller->request()->headers()->array();
        $this->resHeaders = $controller->response()->headers()->array();
        $this->reqBody = $controller->request()->payload()->array();
        $this->resBody = $body;
        $this->errors = $k->errors()->all();

        // Database Queries
        $this->dbQueries = [];
        foreach ($k->db()->getAllQueries() as $dbQuery) {
            /** @var \Comely\Database\Queries\Query $dbQueryInstance */
            $dbQueryInstance = $dbQuery["query"];
            $thisQuery = [
                "db" => $dbQuery["db"],
                "query" => [
                    "sql" => $dbQueryInstance->query(),
                    "data" => json_encode($dbQueryInstance->data()),
                    "executed" => $dbQueryInstance->executed(),
                    "rows" => $dbQueryInstance->rows(),
                    "error" => null,
                ]
            ];

            if ($dbQueryInstance->error()) {
                $thisQuery["error"] = [
                    "code" => $dbQueryInstance->error()->code,
                    "info" => $dbQueryInstance->error()->info,
                    "sqlState" => $dbQueryInstance->error()->sqlState
                ];
            }

            $this->dbQueries[] = $thisQuery;
            unset($thisQuery);
        }
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return [
            "query" => $this->query,
            "reqHeaders" => $this->reqHeaders,
            "resHeaders" => $this->resHeaders,
            "reqBody" => $this->reqBody,
            "resBody" => $this->resBody,
            "dbQueries" => $this->dbQueries,
            "errors" => $this->errors
        ];
    }
}
