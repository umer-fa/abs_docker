<?php
declare(strict_types=1);

namespace App\Common\Mailer;

use App\Common\Kernel;
use Comely\Knit\Knit;

/**
 * Class Mailer
 * @package App\Common\Mailer
 */
class Mailer
{
    /** @var Mailer|null */
    protected static ?Mailer $instance = null;

    /**
     * @return static
     */
    public static function getInstance(): self
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /** @var Kernel */
    private Kernel $app;
    /** @var Knit */
    private Knit $knit;

    /**
     * Mailer constructor.
     * @throws \App\Common\Exception\AppDirException
     * @throws \Comely\Filesystem\Exception\PathException
     */
    private function __construct()
    {
        $this->app = Kernel::getInstance();

        // Prepare knit
        $this->knit = new Knit();
        $this->knit->dirs()->templates($this->app->dirs()->emails());
        $this->knit->dirs()->compiler($this->app->dirs()->knit()->dir("emails", true));
        $this->knit->modifiers()->registerDefaultModifiers();
    }

    /**
     * @return Knit
     */
    public function knit(): Knit
    {
        return $this->knit;
    }

    /**
     * @param string $to
     * @param string $subject
     * @return MailConstructor
     * @throws \App\Common\Exception\MailConstructException
     */
    public function compose(string $to, string $subject): MailConstructor
    {
        return new MailConstructor($to, $subject);
    }
}
