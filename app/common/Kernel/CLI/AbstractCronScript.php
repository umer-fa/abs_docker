<?php
declare(strict_types=1);

namespace App\Common\Kernel\CLI;

use Comely\Utils\OOP\OOP;
use FurqanSiddiqui\SemaphoreEmulator\Exception\ConcurrentRequestTimeout;
use FurqanSiddiqui\SemaphoreEmulator\ResourceLock;

/**
 * Class AbstractCronScript
 * @package App\Common\Kernel\CLI
 */
abstract class AbstractCronScript extends AbstractCLIScript
{
    public const DISPLAY_HEADER = false;
    public const DISPLAY_LOADED_NAME = false;

    public const SEMPAHORE_LOCK = null;
    public const SEMAPHORE_TIMEOUT = 10;

    /** @var string */
    protected string $scriptClassName;
    /** @var ResourceLock|null */
    protected ?ResourceLock $semaphore = null;

    /**
     * @throws \Exception
     */
    final public function exec(): void
    {
        $this->scriptClassName = OOP::baseClassName(get_called_class());

        // Sempahore Lock Check
        $semaphoreLockId = static::SEMPAHORE_LOCK;
        if (is_string($semaphoreLockId)) {
            try {
                $this->semaphore = $this->app->semaphoreEmulator()->obtainLock($semaphoreLockId, null, 10);
                $this->semaphore->setAutoRelease();
            } catch (\Exception $e) {
                if ($e instanceof ConcurrentRequestTimeout) {
                    $this->print(sprintf('{red}Another process for this {cyan}%s{/}{red} is running...{/}', $this->scriptClassName));
                    return;
                }

                throw $e;
            }
        }

        $this->execCron();
    }

    /**
     * @throws \FurqanSiddiqui\SemaphoreEmulator\Exception\ResourceLockException
     */
    final public function releaseSemaphoreLock(): void
    {
        if ($this->semaphore) {
            $this->semaphore->release();
        }
    }

    /**
     * @return void
     */
    abstract public function execCron(): void;
}
