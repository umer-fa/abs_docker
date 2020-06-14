<?php
declare(strict_types=1);

namespace App\Common;

use App\Common\Exception\AppException;

/**
 * Class Validator
 * @package App\Common
 */
class Validator
{
    /**
     * @param $username
     * @return bool
     */
    public static function isValidUsername($username): bool
    {
        return is_string($username) && preg_match('/^[a-z0-9]+[a-z0-9_\-.]{1,19}$/i', $username);
    }

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
     * @param string $amount
     * @param int $retain
     * @return string
     */
    public static function cleanDecimalDigits(string $amount, int $retain = 0): string
    {
        $clean = strpos($amount, ".") !== false ? rtrim(rtrim($amount, "0"), ".") : $amount;
        if ($retain) {
            $amount = explode(".", $clean);
            $decimals = $amount[1] ?? "";
            $has = strlen($decimals);
            $needed = $retain - $has;
            if ($needed > 0) {
                $clean = $amount[0] . "." . $decimals . str_repeat("0", $needed);
            }
        }

        return $clean;
    }

    /**
     * @param $ip
     * @param bool $allowV6
     * @return bool
     */
    public static function isValidIP($ip, bool $allowV6 = true): bool
    {
        if (!is_string($ip) || !$ip) {
            return false;
        }

        $flags = FILTER_FLAG_IPV4;
        if ($allowV6) {
            $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) ? true : false;
    }

    /**
     * @param $num
     * @param int $min
     * @return bool
     */
    public static function isValidPort($num, int $min = 0x03e8): bool
    {
        if (is_int($num)) {
            if ($num >= $min || $num <= 0xffff) {
                return true;
            }
        }

        return false;
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

    /**
     * @param $obj
     * @param string $label
     * @return array
     * @throws AppException
     */
    public static function JSON_Filter($obj, string $label): array
    {
        if (!is_array($obj) && !is_object($obj)) {
            throw new \InvalidArgumentException('JSON filter may only be applied to Array or Objects');
        }

        $json = json_encode($obj);
        if (!$json) {
            $error = json_last_error_msg();
            $exception = sprintf('Failed to JSON encode "%s" object', $label);
            if ($error) {
                $exception .= "; " . $error;
            }

            throw new AppException($exception);
        }

        $filtered = json_decode($json, true);
        if (!is_array($filtered)) {
            $error = json_last_error_msg();
            $exception = sprintf('Failed to apply JSON filter on "%s" object', $label);
            if ($error) {
                $exception .= "; " . $error;
            }

            throw new AppException($exception);
        }

        return $filtered;
    }

    /**
     * @param $email
     * @return bool
     */
    public static function isValidEmailAddress($email): bool
    {
        if (!is_string($email) || !$email) {
            return false;
        }

        return (filter_var($email, FILTER_VALIDATE_EMAIL) && Validator::isASCII($email, "@-._"));
    }

    /**
     * @param $value
     * @param string|null $allow
     * @return bool
     */
    public static function isASCII($value, ?string $allow = null): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $allowed = $allow ? preg_quote($allow, "/") : "";
        $match = '/^[\w\s' . $allowed . ']*$/';

        return preg_match($match, $value) ? true : false;
    }

    /**
     * @param $value
     * @return bool
     */
    public static function isUTF8($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return strlen($value) !== mb_strlen($value);
    }
}
