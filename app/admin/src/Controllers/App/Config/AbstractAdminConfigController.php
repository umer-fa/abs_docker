<?php
declare(strict_types=1);

namespace App\Admin\Controllers\App\Config;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Exception\AppControllerException;

/**
 * Class AbstractAdminConfigController
 * @package App\Admin\Controllers\App\Config
 */
abstract class AbstractAdminConfigController extends AbstractAdminController
{
    protected const LET_VIEW_CONFIG = false;

    /**
     * @throws AppControllerException
     * @throws \App\Common\Exception\AppException
     */
    public function adminCallback(): void
    {
        $privileges = $this->authAdmin->privileges();
        if (!$privileges->root()) {
            if (!$privileges->viewConfig && !static::LET_VIEW_CONFIG) {
                $this->flash()->danger('You are not privileged to view configuration');
                $this->redirect($this->authRoot . "dashboard");
                exit;
            }
        }

        if ($this->request()->method() === "POST") {
            if (!$privileges->root()) {
                if (!$privileges->editConfig) {
                    throw new AppControllerException('You are not privileged to edit configuration');
                }
            }
        }

        $this->configCallback();
    }

    abstract public function configCallback(): void;
}
