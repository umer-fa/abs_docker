<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Exception\AppDirException;
use App\Common\Kernel\Traits\NoDumpTrait;
use App\Common\Kernel\Traits\NotCloneableTrait;
use App\Common\Kernel\Traits\NotSerializableTrait;
use Comely\Filesystem\Directory;
use Comely\Filesystem\Exception\FilesystemException;
use Comely\Filesystem\Exception\PathNotExistException;

/**
 * Class Directories
 * @package App\Common\Kernel
 */
class Directories
{
    /** @var Directory */
    private Directory $root;
    /** @var Directory|null */
    private ?Directory $config = null;
    /** @var Directory|null */
    private ?Directory $storage = null;
    /** @var Directory|null */
    private ?Directory $log = null;
    /** @var Directory|null */
    private ?Directory $tmp = null;
    /** @var Directory|null */
    private ?Directory $sess = null;
    /** @var Directory|null */
    private ?Directory $knit = null;
    /** @var Directory|null */
    private ?Directory $emails = null;
    /** @var Directory|null */
    private ?Directory $semaphore = null;

    use NoDumpTrait;
    use NotCloneableTrait;
    use NotSerializableTrait;

    /**
     * Directories constructor.
     * @throws \Comely\Filesystem\Exception\PathException
     * @throws \Comely\Filesystem\Exception\PathNotExistException
     */
    public function __construct()
    {
        $this->root = new Directory(dirname(__FILE__, 3));
    }

    /**
     * @return Directory
     */
    public function root(): Directory
    {
        return $this->root;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function config(): Directory
    {
        if (!$this->config) {
            $this->config = $this->dir("config", "/config", false);
        }

        return $this->config;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function storage(): Directory
    {
        if (!$this->storage) {
            $this->storage = $this->dir("storage", "/storage", true);
        }

        return $this->storage;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function tmp(): Directory
    {
        if (!$this->tmp) {
            $this->tmp = $this->dir("tmp", "/tmp", true);
        }

        return $this->tmp;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function sempahore(): Directory
    {
        if (!$this->semaphore) {
            $this->semaphore = $this->dir("semaphore", "/tmp/semaphore", true);
        }

        return $this->semaphore;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function emails(): Directory
    {
        if (!$this->emails) {
            $this->emails = $this->dir("emails", "/emails", true);
        }

        return $this->emails;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function sessions(): Directory
    {
        if (!$this->sess) {
            $this->sess = $this->dir("sessions", "/tmp/sessions", true);
        }

        return $this->sess;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function knit(): Directory
    {
        if (!$this->knit) {
            $this->knit = $this->dir("knit", "/tmp/knit", true);
        }

        return $this->knit;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function log(): Directory
    {
        if (!$this->log) {
            $this->log = $this->dir("log", "/log", true);
        }

        return $this->log;
    }

    /**
     * @param string $prop
     * @param string $path
     * @param bool $checkWritable
     * @return Directory
     * @throws AppDirException
     */
    private function dir(string $prop, string $path, bool $checkWritable = false): Directory
    {
        try {
            $dir = $this->root->dir($path);
            if (!$dir->permissions()->read()) {
                throw new AppDirException(sprintf('App directory [:%s] is not readable', $prop));
            }

            if ($checkWritable && !$dir->permissions()->write()) {
                throw new AppDirException(sprintf('App directory [:%s] is not writable', $prop));
            }
        } catch (PathNotExistException $e) {
            throw new AppDirException(sprintf('App directory [:%s] does not exist', $prop));
        } catch (FilesystemException $e) {
            throw new AppDirException(
                sprintf('App directory [:%s]: [%s] %s', $prop, get_class($e), $e->getMessage())
            );
        }

        return $dir;
    }
}
