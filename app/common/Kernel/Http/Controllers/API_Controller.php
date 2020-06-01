<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http\Controllers;

use App\Common\Exception\AppControllerException;
use App\Common\Kernel\AbstractHttpApp;
use Comely\Utils\OOP\OOP;

/**
 * Class API_Controller
 * @package App\Common\Kernel\Http\Controllers
 */
abstract class API_Controller extends AbstractAppController
{
    /** @var bool */
    protected const EXPLICIT_METHOD_NAMES = false;

    /** @var AbstractHttpApp */
    protected AbstractHttpApp $app;

    /**
     * @return void
     */
    public function callback(): void
    {
        parent::callback(); // Set AppKernel instance

        // Default response type (despite of ACCEPT header)
        $this->response()->header("content-type", "application/json");

        // Prepare response
        $this->response()->set("status", false);

        // Controller method
        $httpRequestMethod = strtolower($this->request()->method());
        $controllerMethod = $httpRequestMethod;

        // Explicit method name
        if (static::EXPLICIT_METHOD_NAMES) {
            $queryStringMethod = explode("&", $this->request()->url()->query() ?? "")[0];
            if (preg_match('/^\w+$/', $queryStringMethod)) {
                $controllerMethod .= OOP::PascalCase($queryStringMethod);
            }
        }

        // Execute
        try {
            if (!method_exists($this, $controllerMethod)) {
                if ($httpRequestMethod === "options") {
                    $this->response()->set("status", true);
                    $this->response()->set("options", []);
                    return;
                } else {
                    throw new AppControllerException(
                        sprintf('Endpoint "%s" does not support "%s" method', get_called_class(), strtoupper($controllerMethod))
                    );
                }
            }

            $this->onLoad(); // Event callback: onLoad
            call_user_func([$this, $controllerMethod]);
        } catch (\Exception $e) {
            $this->response()->set("status", false);
            $this->response()->set("error", $e->getMessage());

            if ($e instanceof AppControllerException) {
                $param = $e->getParam();
                if ($param) {
                    $this->response()->set("param", $param);
                }
            }

            if ($this->app->isDebug()) {
                $this->response()->set("caught", get_class($e));
                $this->response()->set("file", $e->getFile());
                $this->response()->set("line", $e->getLine());
                $this->response()->set("trace", $this->getExceptionTrace($e));
            }
        }

        $displayErrors = $this->app->isDebug() ?
            $this->app->errors()->all() :
            $this->app->errors()->triggered()->array();

        if ($displayErrors) {
            $this->response()->set("errors", $displayErrors); // Errors
        }

        $this->onFinish(); // Event callback: onFinish
    }

    /**
     * @param bool $status
     * @return $this
     */
    final public function status(bool $status): self
    {
        $this->response()->set("status", $status);
        return $this;
    }

    /**
     * @return void
     */
    abstract public function onLoad(): void;

    /**
     * @return void
     */
    abstract public function onFinish(): void;
}
