<?php
declare(strict_types=1);

namespace App\Common\API;

use App\Common\Database\API\Baggage;
use App\Common\Exception\AppException;
use App\Common\Kernel;

/**
 * Class APISessBaggage
 * @package App\Common\API
 */
class APISessBaggage
{
    /** @var Kernel */
    private Kernel $app;
    /** @var API_Session */
    private API_Session $sess;

    /**
     * Baggage constructor.
     * @param API_Session $sess
     */
    public function __construct(API_Session $sess)
    {
        $this->app = Kernel::getInstance();
        $this->sess = $sess;
    }

    /**
     * @param string $key
     * @param string $data
     * @param bool $flash
     * @return bool
     * @throws AppException
     */
    public function set(string $key, string $data, bool $flash = false): bool
    {
        $key = $this->validateKey($key);
        $dataLen = strlen($data);
        if ($dataLen > 10240) {
            throw new AppException(
                sprintf('Data for API baggage "%s" exceeds maximum length of 10240 bytes', $key)
            );
        }

        $query = 'INSERT INTO `%s` (`token`, `key`, `flash`, `data`, `time_stamp`) VALUES (:token, :key, :flash,' .
            ':data, :timeStamp) ON DUPLICATE KEY UPDATE `flash`=:flash, `data`=:data, `time_stamp`=:timeStamp';

        $data = [
            "token" => $this->sess->private("token"),
            "key" => $key,
            "flash" => $flash ? 1 : 0,
            "data" => trim($data),
            "timeStamp" => time()
        ];

        try {
            $db = $this->app->db()->apiLogs();
            $save = $db->exec(sprintf($query, Baggage::NAME), $data);
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
        }

        if (isset($save) && $save->isSuccess(true)) {
            return true;
        }

        throw new AppException(sprintf('Failed to save API session baggage key "%s"', $key));
    }

    /**
     * @param string $key
     * @return string|null
     * @throws AppException
     */
    public function get(string $key): ?string
    {
        $key = $this->validateKey($key);

        try {
            $db = $this->app->db()->apiLogs();
            $fetch = $db->query()->table(Baggage::NAME)
                ->where('`token`=? AND `key`=?', [$this->sess->private("token"), $key])
                ->fetch();
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(sprintf('Failed to retrieve value of API session baggage key "%s"', $key));
        }

        // No rows?
        $first = $fetch->first();
        if (!$fetch->count() || !is_array($first) || !array_key_exists("key", $first)) {
            return null;
        }

        // Is Flash?
        if ($first["flash"] === 1) {
            $this->delete($key);
        }

        return $first["data"];
    }

    /**
     * @param string $key
     * @return bool
     * @throws AppException
     */
    public function delete(string $key): bool
    {
        $key = $this->validateKey($key);

        try {
            $db = $this->app->db()->apiLogs();
            $delete = $db->query()
                ->table(Baggage::NAME)
                ->where('`token`=? AND `key`=?', [$this->sess->private("token"), $key])
                ->delete();
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
        }

        if (isset($delete) && $delete->isSuccess(false)) {
            return $delete->rows() ? true : false;
        }

        throw new AppException(sprintf('Failed to delete API session baggage key "%s"', $key));
    }

    /**
     * @return int
     * @throws AppException
     */
    public function flush(): int
    {
        try {
            $db = $this->app->db()->apiLogs();
            $flush = $db->query()
                ->table(Baggage::NAME)
                ->where('`token`=?', [$this->sess->private("token")])
                ->delete();
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
        }

        if (isset($flush) && $flush->isSuccess(false)) {
            return $flush->rows();
        }

        throw new AppException('Failed to flush API session baggage');
    }

    /**
     * @param string $key
     * @return string
     * @throws AppException
     */
    private function validateKey(string $key): string
    {
        $key = strtolower($key);
        if (!preg_match('/^[\w\-.]{1,32}$/', $key)) {
            throw new AppException('Invalid API session baggage key');
        }

        return $key;
    }
}
