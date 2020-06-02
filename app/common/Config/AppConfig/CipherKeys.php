<?php
declare(strict_types=1);

namespace App\Common\Config\AppConfig;

use App\Common\Exception\AppConfigException;
use Comely\DataTypes\Buffer\Base16;
use Comely\DataTypes\Buffer\Binary;

/**
 * Class CipherKeys
 * @package App\Common\Config\AppConfig
 */
class CipherKeys
{
    /** @var array */
    private array $keys = [];

    /**
     * CipherKeys constructor.
     * @param array $keys
     * @throws AppConfigException
     */
    public function __construct(array $keys)
    {
        $index = 0;
        foreach ($keys as $label => $entropy) {
            if (!preg_match('/^\w{2,16}$/', $label)) {
                throw new AppConfigException(sprintf('Invalid cipher key label at index %d', $index));
            }

            if (!is_string($entropy) || !$entropy) {
                throw new AppConfigException(sprintf('Invalid entropy for cipher key "%s"', $label));
            }

            if (!preg_match('/^[a-f0-9]{64}$/i', $entropy)) {
                $entropy = hash("sha256", $entropy);
            }

            $this->keys[strtolower($label)] = (new Base16($entropy))->binary();
        }
    }

    /**
     * @param string $name
     * @return Binary|null
     */
    public function get(string $name): ?Binary
    {
        return $this->keys[strtolower($name)] ?? null;
    }
}
