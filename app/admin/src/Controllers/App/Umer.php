<?php
declare(strict_types=1);

namespace App\Admin\Controllers\App;

use App\Common\Exception\AppException;
use App\Common\Validator;
use Comely\Database\Database;

/**
 * Class Dashboard
 * @package App\Admin\Controllers
 */
class Umer extends AbstractAdminController
{
    public function adminCallback(): void
    {
    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Countries')->index(310, 40)
            ->prop("icon", "mdi mdi-earth");

        $this->breadcrumbs("Application", null, "mdi mdi-server-network");

        try {
            $countries = Validator::JSON_Filter($this->countries, "Countries");
        } catch (AppException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            $countries = [];
        }

        $template = $this->template("app/countries.knit")
            ->assign("countries", $countries);
        $this->body($template);
    }
}
