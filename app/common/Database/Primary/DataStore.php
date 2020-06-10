<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\Database\AbstractAppTable;
use App\Common\Exception\AppException;
use App\Common\Kernel;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;
use Comely\DataTypes\Buffer\Binary;
use Comely\DataTypes\Integers;

/**
 * Class DataStore
 * @package App\Common\Database\Primary
 */
class DataStore extends AbstractAppTable
{
    public const NAME = 'd_storage';
    public const MODEL = null;
    public const DATA_MAX_BYTES = 10240;

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->string("key")->length(40)->unique();
        $cols->binary("data")->length(self::DATA_MAX_BYTES);
        $cols->string("raw")->length(255)->nullable();
        $cols->int("time_stamp")->bytes(4)->unSigned();
    }

    /**
     * @param string $key
     * @param Binary $data
     * @param string|null $raw
     * @throws AppException
     */
    public static function Save(string $key, Binary $data, ?string $raw = null): void
    {
        $k = Kernel::getInstance();

        if (!preg_match('/^[\w\-.]{8,40}$/', $key)) {
            throw new \InvalidArgumentException('Invalid DataStore object key');
        } elseif (!Integers::Range($data->size()->bytes(), 1, self::DATA_MAX_BYTES)) {
            throw new \RangeException(sprintf('Size for object "%s" cannot exceed %d bytes', $key, self::DATA_MAX_BYTES));
        }

        $query = 'INSERT INTO `%s` (`key`, `data`, `raw`, `time_stamp`) VALUES (:key, :data, :raw, :timeStamp) ' .
            'ON DUPLICATE KEY UPDATE `data`=:data, `time_stamp`=:timeStamp';
        $queryData = [
            "key" => $key,
            "data" => $data->raw(),
            "raw" => $raw,
            "timeStamp" => time()
        ];

        try {
            $db = $k->db()->primary();
            $db->exec(sprintf($query, self::NAME), $queryData)->checkSuccess(true);
        } catch (\Exception $e) {
            $k->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(sprintf('Failed to save "%s" in database', $key));
        }
    }
}
