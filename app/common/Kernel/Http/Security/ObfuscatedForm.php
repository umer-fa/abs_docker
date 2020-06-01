<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http\Security;

use Comely\Http\Query\Payload;
use Comely\Utils\Security\Exception\PRNG_Exception;
use Comely\Utils\Security\PRNG;

/**
 * Class ObfuscatedForm
 * @package App\Common\Kernel\Http\Security
 */
class ObfuscatedForm implements \Serializable
{
    public const FIELD_BYTE_LEN = 6;
    public const SIGNAL_RETRY = 0x07d0;

    /** @var string */
    private string $name;
    /** @var array */
    private array $obfuscated;
    /** @var array */
    private array $fields;
    /** @var int */
    private int $count;
    /** @var string */
    private string $hash;
    /** @var null|Payload */
    private ?Payload $payload;

    /**
     * ObfuscatedForm constructor.
     * @param string $name
     * @param array $fields
     */
    public function __construct(string $name, array $fields)
    {
        if (!preg_match('/^\w{3,32}$/', $name)) {
            throw new \InvalidArgumentException('Invalid obfuscation form name');
        }

        $this->name = $name;
        $this->obfuscated = [];
        $this->fields = [];
        $this->count = 0;

        $count = count($fields);
        if (!$count) {
            throw new \InvalidArgumentException('No fields for obfuscation form');
        }

        try {
            $prngBytesNeeded = $count * self::FIELD_BYTE_LEN;
            $entropy = PRNG::randomBytes($prngBytesNeeded + 1)->base16()->value();
        } catch (PRNG_Exception $e) {
            throw new \RuntimeException('Failed to generate PRNG entropy');
        }

        $obfuscated = str_split($entropy, (self::FIELD_BYTE_LEN * 2));
        if (count($obfuscated) !== count(array_unique($obfuscated))) {
            // A repeating key detected, retry!
            throw new \RuntimeException(
                'Collision of obfuscated keys detected, attempt retry', self::SIGNAL_RETRY
            );
        }

        // Obfuscate fields
        $pos = 0;
        $hash = "";
        foreach ($fields as $field) {
            if (!is_string($field) || !preg_match('/^[\w\-.]{2,32}$/', $field)) {
                throw new \UnexpectedValueException(
                    sprintf('Invalid field name for obfuscated form at position %d', $pos)
                );
            }

            $key = $obfuscated[$pos];
            if (preg_match('/^[0-9]+$/', $key)) {
                $key[0] = chr(97 + intval($key[0])); // Force numerical keys into alphanumeric
            }

            $hash .= $key . "+" . $field . ";";
            $this->obfuscated[$key] = $field;
            $this->fields[$field] = $key;
            $this->count++;
            $pos++;
        }

        // Hash
        $this->hash = hash("sha1", $hash);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function hash(): string
    {
        return $this->hash;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return [
            "name" => $this->name,
            "hash" => $this->hash,
            "fields" => $this->fields
        ];
    }

    /**
     * @return string
     */
    public function serialize(): string
    {
        return base64_encode(json_encode($this->array()));
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $form = json_decode(base64_decode($serialized), true);
        if (!is_array($form) || !isset($form["name"], $form["hash"], $form["fields"])) {
            throw new \UnexpectedValueException('Bad serialized obfuscated form data');
        }

        $this->name = $form["name"];
        $this->hash = $form["hash"];
        $this->fields = $form["fields"];
        $this->obfuscated = array_flip($this->fields);
        $this->count = count($this->fields);
    }

    /**
     * @param string $field
     * @return string|null
     */
    public function key(string $field): ?string
    {
        return $this->fields[$field] ?? null;
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function field(string $key): ?string
    {
        return $this->obfuscated[$key] ?? null;
    }

    /**
     * @param Payload $payload
     * @return $this
     */
    public function input(Payload $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * @param string $field
     * @return array|float|int|string|null
     */
    public function value(string $field)
    {
        if (!$this->payload) {
            throw new \DomainException('Input payload not specified to obfuscated form');
        }

        $field = $this->key($field);
        if ($field) {
            return $this->payload->get($field);
        }

        return null;
    }
}
