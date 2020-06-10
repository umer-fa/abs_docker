<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Exception\AppConfigException;
use App\Common\Kernel;
use Comely\DataTypes\Buffer\Binary;
use Comely\Utils\Security\Cipher;
use Comely\Utils\Security\Exception\CipherException;

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
    private string $defEntropy;

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
        $this->defEntropy = hash("sha256", "enter some random words or PRNG entropy here", true);
    }

    /**
     * @return Cipher
     * @throws AppConfigException
     */
    public function primary(): Cipher
    {
        return $this->get("primary");
    }

    /**
     * @return Cipher
     * @throws AppConfigException
     */
    public function secondary(): Cipher
    {
        return $this->get("secondary");
    }

    /**
     * @return Cipher
     * @throws AppConfigException
     */
    public function users(): Cipher
    {
        return $this->get("users");
    }

    /**
     * @return Cipher
     * @throws AppConfigException
     */
    public function project(): Cipher
    {
        return $this->get("project");
    }

    /**
     * @return Cipher
     * @throws AppConfigException
     */
    public function misc(): Cipher
    {
        return $this->get("misc");
    }

    /**
     * @param string $key
     * @return Cipher
     * @throws AppConfigException
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
        } elseif ($entropy === $this->defEntropy) {
            throw new AppConfigException(sprintf('Cipher key "%s" entropy is set to default; It must be changed', $key));
        }

        try {
            $cipher = new Cipher(new Binary($entropy));
        } catch (CipherException $e) {
            $this->kernel->errors()->trigger($e, E_USER_WARNING);
            throw new AppConfigException(sprintf('Failed to instantiate "%s" cipher', $key));
        }

        $this->ciphers[$key] = $cipher;
        return $cipher;
    }
}
