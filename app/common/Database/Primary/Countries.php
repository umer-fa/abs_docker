<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\Country;
use App\Common\Database\AbstractAppTable;
use App\Common\Exception\AppException;
use App\Common\Kernel;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Countries
 * @package App\Common\Database\Primary
 */
class Countries extends AbstractAppTable
{
    public const NAME = 'countries';
    public const MODEL = 'App\Common\Country';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->string("name")->length(32);
        $cols->int("status")->bytes(1)->unSigned()->default(1);
        $cols->string("code")->fixed(3)->unique();
        $cols->string("code_short")->fixed(2)->unique();
        $cols->int("dial_code")->bytes(3)->unSigned();
    }

    /**
     * @param string $code
     * @return Country
     * @throws AppException
     */
    public static function get(string $code): Country
    {
        $k = Kernel::getInstance();

        try {
            $code = strtolower($code);
            return $k->memory()->query(sprintf('country_%s', $code), self::MODEL)
                ->fetch(function () use ($code) {
                    return self::Find()->col("code", $code)->limit(1)->first();
                });
        } catch (\Exception $e) {
            if (!$e instanceof ORM_ModelNotFoundException) {
                $k->errors()->triggerIfDebug($e, E_USER_WARNING);
            }

            throw new AppException('No such country is available');
        }
    }

    /**
     * @param int|null $status
     * @return array
     */
    public static function List(?int $status = null): array
    {
        $query = 'WHERE 1 ORDER BY `name` ASC';
        $queryData = null;
        if (is_int($status) && $status > 0) {
            $query = 'WHERE `status`=? ORDER BY `name` ASC';
            $queryData = [$status];
        }

        try {
            return Countries::Find()->query($query, $queryData)->all();
        } catch (\Exception $e) {
            Kernel::getInstance()->errors()->trigger($e, E_USER_WARNING);
        }

        return [];
    }
}
