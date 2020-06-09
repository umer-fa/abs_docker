<?php
declare(strict_types=1);

namespace App\Common\Database\API;

use App\Common\Database\AbstractAppTable;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Baggage
 * @package App\Common\Database\API
 */
class Baggage extends AbstractAppTable
{
    public const NAME = 'api_baggage';
    public const MODEL = null;

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->binary("token")->fixed(32);
        $cols->string("key")->length(32);
        $cols->int("flash")->bytes(1)->unSigned()->default(0);
        $cols->binary("data")->length(10240)->nullable();
        $cols->int("time_stamp")->bytes(4)->unSigned();

        $constraints->uniqueKey("uid")->columns("token", "key");
        $constraints->foreignKey("token")->table(Sessions::NAME, "token");
    }
}
