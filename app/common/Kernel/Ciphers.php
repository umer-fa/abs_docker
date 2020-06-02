<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Exception\AppConfigException;
use App\Common\Kernel;
use Comely\Utils\Security\Cipher;

/**
 * Class Ciphers
 * @package App\Common\Kernel
 */
class Ciphers
{
    /** @var Kernel */
    private Kernel $kernel;
    /** @var array */
    private array $ciphers = [];

    use Kernel\Traits\NoDumpTrait;
    use Kernel\Traits\NotCloneableTrait;
    use Kernel\Traits\NotSerializableTrait;

    /**
     * CipherKeys constructor.
     * @param Kernel $appKernel
     */
    public function __construct(Kernel $appKernel)
    {
        $this->kernel = $appKernel;
    }

    /**
     * @return Cipher
     * @throws AppConfigException
     * @throws \Comely\Utils\Security\Exception\CipherException
     */
    public function primary(): Cipher
    {
        return $this->get("primary");
    }

    /**
     * @return Cipher
     * @throws AppConfigException
     * @throws \Comely\Utils\Security\Exception\CipherException
     */
    public function secondary(): Cipher
    {
        return $this->get("secondary");
    }

    /**
     * @return Cipher
     * @throws AppConfigException
     * @throws \Comely\Utils\Security\Exception\CipherException
     */
    public function users(): Cipher
    {
        return $this->get("users");
    }

    /**
     * @return Cipher
     * @throws AppConfigException
     * @throws \Comely\Utils\Security\Exception\CipherException
     */
    public function project(): Cipher
    {
        return $this->get("project");
    }

    /**
     * @return Cipher
     * @throws AppConfigException
     * @throws \Comely\Utils\Security\Exception\CipherException
     */
    public function misc(): Cipher
    {
        return $this->get("misc");
    }

    /**
     * @param string $key
     * @return Cipher
     * @throws AppConfigException
     * @throws \Comely\Utils\Security\Exception\CipherException
     */
    public function get(string $key): Cipher
    {
        if (!preg_match('/^\w{2,16}$/', $key)) {
            throw new \InvalidArgumentException('Invalid cipher name');
        }

        $key = strtolower($key);
        if (array_key_exists($key, $this->ciphers)) {
            return $this->ciphers[$key];
        }

        $entropy = $this->kernel->config()->cipher()->get($key);
        if (!$entropy) {
            throw new AppConfigException(sprintf('Cipher key "%s" does not exist', $key));
        }

        $defaultEntropy = hash("sha256", "enter some random words or PRNG entropy here", false);
        if (hash_equals($defaultEntropy, $entropy->base16()->hexits())) {
            throw new AppConfigException(
                sprintf('Cipher key "%s" is set to default value; Please change it first', $key)
            );
        }

        $cipher = new Cipher($entropy);
        $this->ciphers[$key] = $cipher;
        return $cipher;
    }
}
