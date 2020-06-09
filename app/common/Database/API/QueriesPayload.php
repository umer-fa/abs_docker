<?php
declare(strict_types=1);

namespace App\Common\Database\API;

use App\Common\Database\AbstractAppTable;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class QueriesPayload
 * @package App\Common\Database\API
 */
class QueriesPayload extends AbstractAppTable
{
    public const NAME = 'api_queries_payload';
    public const MODEL = null;

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("query")->bytes(8)->unSigned()->unique();
        $cols->blob("encrypted")->size("medium");

        $constraints->foreignKey("query")->table(Queries::NAME, "id");
    }
}
