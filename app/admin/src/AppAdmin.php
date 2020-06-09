<?php
declare(strict_types=1);

namespace App\Admin;

use App\Common\Kernel;
use Comely\Knit\Knit;
use Comely\Sessions\Sessions;
use Comely\Sessions\Storage\SessionDirectory;

/**
 * Class AppAdmin
 * @package App\Admin
 */
class AppAdmin extends Kernel\AbstractHttpApp
{
    /** @var Sessions */
    private Sessions $sess;
    /** @var Knit */
    private Knit $knit;

    /**
     * AppAdmin constructor.
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\AppDirException
     * @throws \Comely\Filesystem\Exception\PathException
     * @throws \Comely\Filesystem\Exception\PathNotExistException
     * @throws \Comely\Filesystem\Exception\PathOpException
     * @throws \Comely\Sessions\Exception\StorageException
     * @throws \Comely\Yaml\Exception\ParserException
     */
    public function __construct()
    {
        parent::__construct();

        // Sessions
        $this->sess = new Sessions(new SessionDirectory($this->dirs()->sessions()));

        // Knit
        $this->knit = new Knit();
        $this->knit->dirs()->compiler($this->dirs()->knit())
            ->cache($this->dirs()->knit()->dir("cache"));

        $this->knit->modifiers()->registerDefaultModifiers();
    }

    /**
     * @return Sessions
     */
    public function sessions(): Sessions
    {
        return $this->sess;
    }

    /**
     * @return Knit
     */
    public function knit(): Knit
    {
        return $this->knit;
    }
}
