<?php
declare(strict_types=1);

namespace App\Common\Config\AppConfig;

use App\Common\Exception\AppConfigException;
use Comely\Utils\Validator\Exception\InvalidValueException;
use Comely\Utils\Validator\Exception\ValidationException;
use Comely\Utils\Validator\Validator;

/**
 * Class DbCred
 * @package App\Common\Config\AppConfig
 */
class DbCred
{
    /** @var string DB label */
    public string $label;
    /** @var string DB driver */
    public string $driver;
    /** @var string DB host */
    public string $host;
    /** @var int|null DB port */
    public ?int $port = null;
    /** @var string Database name */
    public string $name;
    /** @var string|null DB username */
    public ?string $username = null;
    /** @var string|null DB password */
    public ?string $password = null;

    /**
     * DbCred constructor.
     * @param string $label
     * @param array $args
     * @throws AppConfigException
     */
    public function __construct(string $label, array $args)
    {
        $this->label = $label;

        try {
            /** @var string $driver */
            $driver = Validator::String($args["driver"])->lowerCase()->inArray(["mysql", "pgsql", "sqlite"])->validate();
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('DB[%s]: [%s] Invalid database driver', $label, get_class($e)));
        }

        $this->driver = $driver;

        try {
            /** @var string $host */
            $host = Validator::String($args["host"])->validate(function ($hostname) {
                $hostname = \App\Common\Validator::isValidHostname($hostname);
                if (!is_string($hostname)) {
                    throw new InvalidValueException();
                }

                return $hostname;
            });
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('DB[%s]: [%s] Invalid DB hostname', $label, get_class($e)));
        }

        $this->host = $host;

        try {
            $port = Validator::Integer($args["port"])->range(1000, 0xffff)->nullable()->validate();
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('DB[%s]: [%s] Invalid DB port', $label, get_class($e)));
        }

        $this->port = $port;

        try {
            /** @var string $name */
            $name = Validator::String($args["name"])->match('/^[\w\-]{3,32}$/')->validate();
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('DB[%s]: [%s] Invalid DB name', $label, get_class($e)));
        }

        $this->name = $name;

        try {
            $username = Validator::String($args["username"])->nullable()->match('/^\w{3,32}$/')->validate();
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('DB[%s]: [%s] Invalid DB username', $label, get_class($e)));
        }

        $this->username = $username;

        try {
            $password = Validator::String($args["password"])->nullable()->match('/^\w{3,64}$/')->validate();
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('DB[%s]: [%s] Invalid DB password', $label, get_class($e)));
        }

        $this->password = $password;

        unset($label, $driver, $host, $name, $port, $username, $password);
    }
}
