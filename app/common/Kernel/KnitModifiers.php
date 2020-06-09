<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use Comely\Knit\Knit;

/**
 * Class KnitModifiers
 * @package App\Common\Kernel
 */
class KnitModifiers
{
    /**
     * @param Knit $knit
     */
    public static function CleanDecimals(Knit $knit): void
    {
        $knit->modifiers()->register("cleanDecimals", function (string $var) {
            return sprintf("App\Common\Validator::cleanDecimalDigits(%s)", $var);
        });
    }

    /**
     * @param Knit $knit
     */
    public static function Dated(Knit $knit): void
    {
        $knit->modifiers()->register("dated", function (string $var) {
            return sprintf('date("d M Y H:i", %s)', $var);
        });
    }

    /**
     * @param Knit $knit
     */
    public static function Null(Knit $knit): void
    {
        $knit->modifiers()->register("null", function (string $var) {
            return sprintf('%s ?? null', $var);
        });
    }

    /**
     * @param Knit $knit
     */
    public static function Hex(Knit $knit): void
    {
        $knit->modifiers()->register("hexdec");
        $knit->modifiers()->register("dechex");
    }
}
