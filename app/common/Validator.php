<?php
declare(strict_types=1);

namespace App\Common;

/**
 * Class Validator
 * @package App\Common
 */
class Validator
{
    /**
     * @param $hostname
     * @return string|null
     */
    public static function isValidHostname($hostname): ?string
    {
        if (!is_string($hostname) || !$hostname) {
            return null;
        }

        $hostname = strtolower($hostname);
        if (preg_match('/^[a-z0-9\-]+(\.[a-z0-9\-]+)*$/', $hostname)) {
            return $hostname; // Validated as Domain
        }

        $filterIP = filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
        return $filterIP ? $filterIP : null;
    }

    /**
     * @param $phone
     * @return bool
     */
    public static function isValidPhone($phone): bool
    {
        return is_string($phone) && preg_match('/^\+[0-9]+\.[0-9]{5,20}$/', $phone);
    }

    /**
     * @param $value
     * @param int|null $min
     * @param int|null $max
     * @return int|null
     */
    public static function UInt($value, ?int $min = null, ?int $max = null): ?int
    {
        try {
            $validator = \Comely\Utils\Validator\Validator::Integer($value)->unSigned();
            if ($min && $max) {
                $validator->range($min, $max);
            }

            return $validator->validate();
        } catch (\Comely\Utils\Validator\Exception\ValidationException $e) {
        }

        return null;
    }

    /**
     * @param $val
     * @return bool
     */
    public static function getBool($val): bool
    {
        if (is_bool($val)) {
            return $val;
        }

        if (is_int($val) && $val === 1) {
            return true;
        }

        if (is_string($val) && in_array(strtolower($val), ["1", "true", "on", "yes"])) {
            return true;
        }

        return false;
    }
}
