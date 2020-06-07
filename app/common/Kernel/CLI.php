<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Kernel;
use Comely\CLI\Abstract_CLI_Script;
use Comely\CLI\ASCII\Banners;
use Comely\Filesystem\Directory;
use Comely\Utils\OOP\OOP;

/**
 * Class CLI
 * @package App\Common\Kernel
 */
class CLI extends \Comely\CLI\CLI
{
    /** @var Kernel */
    private Kernel $app;

    /**
     * CLI constructor.
     * @param Kernel $kernel
     * @param Directory $bin
     * @param array $args
     * @throws \Comely\CLI\Exception\BadArgumentException
     */
    public function __construct(Kernel $kernel, Directory $bin, array $args)
    {
        $this->app = $kernel;
        parent::__construct($bin, $args);

        // Events
        $this->events()->scriptNotFound()->listen(function (\Comely\CLI\CLI $cli, string $scriptClassName) {
            $this->printAppHeader();
            $cli->print(sprintf("CLI script {red}{invert} %s {/} not found", OOP::baseClassName($scriptClassName)));
            $cli->print("");
        });

        $this->events()->scriptLoaded()->listen(function (\Comely\CLI\CLI $cli, Abstract_CLI_Script $script) {
            $displayHeader = @constant($this->execClassName . "::DISPLAY_HEADER") ?? true;
            if ($displayHeader) {
                $this->printAppHeader();
            }

            $displayLoadedName = @constant($this->execClassName . "::DISPLAY_LOADED_NAME") ?? true;
            if ($displayLoadedName) {
                $cli->inline(sprintf('CLI script {green}{invert} %s {/} loaded', OOP::baseClassName(get_class($script))));
                $cli->repeat(".", 3, 100, true);
                $cli->print("");
            }
        });

        $this->events()->afterExec()->listen(function () {
            $displayErrors = @constant($this->execClassName . "::DISPLAY_TRIGGERED_ERRORS") ?? true;
            if ($displayErrors) {
                $errors = $this->app->errors()->all();
                $errorsCount = $this->app->errors()->count();

                $this->print("");
                if ($errorsCount) {
                    $this->repeat(".", 10, 50, true);
                    $this->print("");
                    $this->print(sprintf("{red}{invert} %d {/}{red}{b} triggered errors!{/}", $errorsCount));
                    /** @var Kernel\ErrorHandler\ErrorMsg $error */
                    foreach ($errors as $error) {
                        $this->print(sprintf('{grey}│  ┌ {/}{yellow}Type:{/} {magenta}%s{/}', strtoupper($error->typeStr)));
                        $this->print(sprintf('{grey}├──┼ {/}{yellow}Message:{/} %s', $error->message));
                        $this->print(sprintf("{grey}│  ├ {/}{yellow}File:{/} {cyan}%s{/}", $error->file));
                        $this->print(sprintf("{grey}│  └ {/}{yellow}Line:{/} %d", $error->line ?? -1));
                        $this->print("{grey}│{/}");
                    }

                    $this->print("");
                } else {
                    $this->print("{grey}No triggered errors!{/}");
                }
            }
        });
    }

    /**
     * @return void
     */
    public function printAppHeader(): void
    {
        $this->print(sprintf("{yellow}{invert}Comely App Kernel{/} {grey}v%s{/}", Kernel::VERSION), 200);
        $this->print(sprintf("{cyan}{invert}Comely CLI{/} {grey}v%s{/}", \Comely\CLI\CLI::VERSION), 200);

        // App Introduction
        $this->print("");
        $this->repeat("~", 5, 100, true);
        foreach (Banners::Digital($this->app->constant("name") ?? "Untitled App")->lines() as $line) {
            $this->print("{magenta}{invert}" . $line . "{/}");
        }

        $this->repeat("~", 5, 100, true);
        $this->print("");
    }

    /**
     * @return Kernel
     */
    public function app(): Kernel
    {
        return $this->app;
    }
}
