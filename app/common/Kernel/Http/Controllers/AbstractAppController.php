<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http\Controllers;

use App\Common\Kernel;
use Comely\Http\Router\AbstractController;

/**
 * Class AbstractAppController
 * @package App\Common\Kernel\Http\Controllers
 */
abstract class AbstractAppController extends AbstractController
{
    /**
     * @return void
     */
    public function callback(): void
    {
        $k = Kernel\AbstractHttpApp::getInstance();
        call_user_func([$k->http()->remote(), "set"], $this->request()); // Register REMOTE_* values
    }

    /**
     * @param \Exception $e
     * @return array
     */
    protected function getExceptionTrace(\Exception $e): array
    {
        return array_map(function (array $trace) {
            unset($trace["args"]);
            return $trace;
        }, $e->getTrace());
    }
}
