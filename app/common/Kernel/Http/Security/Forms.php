<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http\Security;

use App\Common\Kernel\AbstractHttpApp;
use Comely\Sessions\ComelySession;

/**
 * Class Forms
 * @package App\Common\Kernel\Http\Security
 */
class Forms
{
    /** @var AbstractHttpApp */
    private AbstractHttpApp $kernel;
    /** @var ComelySession */
    private ComelySession $sess;

    /**
     * Forms constructor.
     * @param AbstractHttpApp $k
     * @param ComelySession $sess
     */
    public function __construct(AbstractHttpApp $k, ComelySession $sess)
    {
        $this->kernel = $k;
        $this->sess = $sess;
    }

    /**
     * @param string $name
     * @param array $fields
     * @return ObfuscatedForm
     */
    public function get(string $name, array $fields): ObfuscatedForm
    {
        return $this->retrieve($name) ?? $this->obfuscate($name, $fields);
    }

    /**
     * @param string $name
     * @param array $fields
     * @return ObfuscatedForm
     */
    public function obfuscate(string $name, array $fields): ObfuscatedForm
    {
        try {
            $form = new ObfuscatedForm($name, $fields);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === ObfuscatedForm::SIGNAL_RETRY) {
                return $this->obfuscate($name, $fields);
            }

            throw $e;
        }

        $this->sess->meta()->bag("obfuscated_forms")->set($form->name(), serialize($form));
        return $form;
    }

    /**
     * @param string $name
     * @return ObfuscatedForm|null
     */
    public function retrieve(string $name): ?ObfuscatedForm
    {
        if (!preg_match('/^\w{3,32}$/', $name)) {
            throw new \InvalidArgumentException('Invalid obfuscated form name');
        }

        $form = $this->sess->meta()->bag("obfuscated_forms")->get($name);
        if (!$form) {
            return null;
        }

        $form = unserialize(strval($form), [
            "allowed_classes" => [
                'App\Common\Kernel\Http\Security\ObfuscatedForm'
            ]
        ]);

        if (!$form instanceof ObfuscatedForm) {
            trigger_error(sprintf('Failed to unserialize obfuscated form "%s"', $name), E_USER_WARNING);
            $this->purge($name);
            return null;
        }

        return $form;
    }

    /**
     * @param string $name
     */
    public function purge(string $name): void
    {
        $this->sess->meta()->bag("obfuscated_forms")->delete($name);
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->sess->meta()->delete("obfuscated_forms");
    }
}
