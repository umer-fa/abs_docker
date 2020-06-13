<?php
declare(strict_types=1);

namespace App\Admin\Controllers\App\Config;

use App\Common\Config\ProgramConfig;
use App\Common\Exception\AppException;
use App\Common\Kernel\KnitModifiers;
use Comely\Utils\Time\Time;
use Comely\Utils\Time\TimeUnits;

/**
 * Class Program
 * @package App\Admin\Controllers\App\Config
 */
class Program extends AbstractAdminConfigController
{
    /** @var ProgramConfig */
    private ProgramConfig $programConfig;

    /**
     * @return void
     */
    public function configCallback(): void
    {
        try {
            $this->programConfig = ProgramConfig::getInstance(true);
        } catch (AppException $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
            $this->programConfig = new ProgramConfig();
        }
    }

    public function post(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck();


    }

    /**
     * @throws AppException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Program Config')->index(310, 0, 1)
            ->prop("icon", "mdi mdi-cog-box");

        $this->breadcrumbs("Application", null, "mdi mdi-server-network");
        $this->breadcrumbs("Configuration", null, "ion ion-ios-settings-strong");

        // Last Cached On
        $lastCachedOn = null;
        if (isset($this->programConfig->cachedOn)) {
            $lastCachedTimeDiff = Time::difference($this->programConfig->cachedOn);
            if ($lastCachedTimeDiff) {
                $timeUnits = new TimeUnits();
                $lastCachedOn = $timeUnits->timeToString($lastCachedTimeDiff);
            }
        }

        KnitModifiers::Null($this->knit());

        $template = $this->template("app/config/program.knit")
            ->assign("lastCachedOn", $lastCachedOn)
            ->assign("programConfig", $this->programConfig);
        $this->body($template);
    }
}
