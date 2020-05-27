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
}
