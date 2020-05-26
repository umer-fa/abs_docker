<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use Comely\Database\Database;

/**
 * Class Databases
 * @package App\Common\Kernel
 */
class Databases
{
    /** @var array */
    private array $dbs = [];

    public function get(string $name = "primary"): Database
    {

    }

    /**
     * @param string $name
     * @param Database $db
     */
    public function append(string $name, Database $db): void
    {
        $this->dbs[strtolower($name)] = $db;
    }
}
