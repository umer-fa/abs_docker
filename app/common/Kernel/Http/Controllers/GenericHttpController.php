<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http\Controllers;

use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Exception\ObfuscatedFormsException;
use App\Common\Exception\XSRF_Exception;
use App\Common\Kernel\AbstractHttpApp;
use App\Common\Kernel\Http\Page;
use App\Common\Kernel\Http\Remote;
use App\Common\Kernel\Http\Response\Messages;
use App\Common\Kernel\Http\Security\Forms;
use App\Common\Kernel\Http\Security\ObfuscatedForm;
use App\Common\Kernel\Http\Security\XSRF;
use Comely\Knit\Exception\KnitException;
use Comely\Knit\Knit;
use Comely\Knit\Template;
use Comely\Sessions\ComelySession;
use Comely\Sessions\Exception\SessionsException;
use Comely\Utils\OOP\OOP;

/**
 * Class GenericHttpController
 * @package App\Common\Kernel\Http\Controllers
 */
abstract class GenericHttpController extends AbstractAppController
{
    /** @var AbstractHttpApp */
    protected AbstractHttpApp $app;

    /** @var Messages */
    private Messages $messages;
    /** @var ComelySession|null */
    private ?ComelySession $session = null;
    /** @var null|Messages */
    private ?Messages $flash = null;
    /** @var null|Page */
    private ?Page $page = null;
    /** @var XSRF */
    private ?XSRF $xsrf = null;
    /** @var null|Forms */
    private ?Forms $obfuscatedForms = null;

    /**
     * @throws \Exception
     */
    public function callback(): void
    {
        parent::callback();
        $this->messages = new Messages();

        $this->response()->header("content-type", "application/json");

        $this->response()->set("status", false);
        $this->response()->set("messages", null);

        // Controller method
        $httpRequestMethod = strtolower($this->request()->method());
        $controllerMethod = $httpRequestMethod;

        // Explicit method name
        $queryStringMethod = explode("&", $this->request()->url()->query() ?? "")[0];
        if (preg_match('/^\w+$/', $queryStringMethod)) {
            $controllerMethod .= OOP::PascalCase($queryStringMethod);
            // If HTTP request method is GET, and assumed method doesn't exist, default controller is "get()"
            if ($httpRequestMethod === "get" && !method_exists($this, $controllerMethod)) {
                $controllerMethod = "get";
            }
        }

        // Execute
        try {
            if (!method_exists($this, $controllerMethod)) {
                throw new AppControllerException(
                    sprintf(
                        'Requested method "%s" not found in HTTP controller "%s" class',
                        $controllerMethod,
                        get_called_class()
                    )
                );
            }

            $this->onLoad(); // Event callback: onLoad
            call_user_func([$this, $controllerMethod]);
        } catch (\Exception $e) {
            if (preg_match('/html/', $this->response()->contentType() ?? "")) {
                throw $e; // Throw caught exception so it may be picked by Exception Handler (screen)
            }

            $param = $e instanceof AppException ? $e->getParam() : null;
            $this->messages->danger($e->getMessage(), $param);
            if ($this->app->isDebug()) {
                $this->response()->set("caught", get_class($e));
                $this->response()->set("file", $e->getFile());
                $this->response()->set("line", $e->getLine());
                $this->response()->set("trace", $this->getExceptionTrace($e));
            }
        }

        $this->response()->set("messages", $this->messages->array()); // Messages

        $displayErrors = $this->app->isDebug() ?
            $this->app->errors()->all() :
            $this->app->errors()->triggered()->array();
        if ($displayErrors) {
            $this->response()->set("errors", $displayErrors); // Errors
        }

        // Set flash messages in session
        $this->storeFlashMessages();

        $this->onFinish(); // Event callback: onFinish
    }

    /**
     * @return void
     */
    private function storeFlashMessages(): void
    {
        if ($this->flash && $this->session) {
            $this->session->flash()->bags()->set("messages", serialize($this->flash));
        }
    }

    /**
     * @param string $url
     * @param int|null $code
     */
    public function redirect(string $url, ?int $code = null): void
    {
        // Set flash messages in session
        $this->storeFlashMessages();

        parent::redirect($url, $code);
    }

    /**
     * @return void
     */
    abstract public function onLoad(): void;

    /**
     * @return void
     */
    abstract public function onFinish(): void;

    /**
     * @param string|null $id
     * @param bool $setCookie
     * @throws \Comely\Sessions\Exception\ComelySessionException
     */
    protected function initSession(?string $id = null, bool $setCookie = true): void
    {
        $sessionId = $id ?? $_COOKIE["COMELYSESSID"] ?? null;
        if ($sessionId) {
            try {
                $this->session = $this->app->sessions()->resume($sessionId);
            } catch (SessionsException $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        }

        if (!$this->session) {
            $this->session = $this->app->sessions()->start();
        }

        if ($setCookie) {
            $this->app->http()->cookies()->set("COMELYSESSID", $this->session->id());
        }
    }

    /**
     * @return Messages
     */
    public function messages(): Messages
    {
        return $this->messages;
    }

    /**
     * @return Messages
     */
    public function flash(): Messages
    {
        if (!$this->flash) {
            if (!$this->session) {
                throw new \RuntimeException('Flash messages requires session instantiated');
            }

            $this->flash = new Messages();
        }

        return $this->flash;
    }

    /**
     * @return ComelySession
     */
    public function session(): ComelySession
    {
        if (!$this->session) {
            throw new \RuntimeException('Session was not instantiated');
        }

        return $this->session;
    }

    /**
     * @return XSRF
     * @throws XSRF_Exception
     */
    public function xsrf(): XSRF
    {
        if (!$this->xsrf) {
            if (!$this->session) {
                throw new XSRF_Exception('XSRF requires session instantiated');
            }

            $this->xsrf = new XSRF($this->app, $this->session);
        }

        return $this->xsrf;
    }

    /**
     * @return Forms
     * @throws ObfuscatedFormsException
     */
    public function obfuscatedForms(): Forms
    {
        if (!$this->obfuscatedForms) {
            if (!$this->session) {
                throw new ObfuscatedFormsException('Obfuscated forms requires session instantiated');
            }

            $this->obfuscatedForms = new Forms($this->app, $this->session);
        }

        return $this->obfuscatedForms;
    }

    /**
     * @param string $name
     * @param string|null $hashFieldName
     * @return ObfuscatedForm
     * @throws ObfuscatedFormsException
     */
    public function getObfuscatedForm(string $name, ?string $hashFieldName = "form"): ObfuscatedForm
    {
        $form = $this->obfuscatedForms()->retrieve($name);
        if (!$form) {
            throw new ObfuscatedFormsException('Secure obfuscated form not found; Try refreshing the page');
        }

        // Set input payload
        $form->input($this->input());

        // Verify form hash
        if ($hashFieldName) {
            if (!hash_equals($form->hash(), $form->value($hashFieldName) ?? "")) {
                throw new ObfuscatedFormsException('Invalid obfuscated form hash');
            }
        }

        return $form;
    }

    /**
     * @return Knit
     */
    public function knit(): Knit
    {
        return $this->app->knit();
    }

    /**
     * @param string $templateFile
     * @return Template
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function template(string $templateFile): Template
    {
        return $this->knit()->template($templateFile);
    }

    /**
     * @return Page
     */
    public function page(): Page
    {
        if (!$this->page) {
            $this->page = new Page($this);
        }

        return $this->page;
    }

    /**
     * @return Remote
     */
    public function remote(): Remote
    {
        return $this->app->http()->remote();
    }

    /**
     * @param Template $template
     * @throws KnitException
     */
    public function body(Template $template): void
    {
        try {
            // Flash messages
            $flashMessages = null;
            if ($this->session) {
                $serializedFlash = $this->session->flash()->last()->get("messages");
                if ($serializedFlash) {
                    $flashMessages = unserialize($serializedFlash, [
                        "allowed_classes" => [
                            'App\Common\Kernel\Http\Response\Messages'
                        ]
                    ]);

                    if (!$flashMessages instanceof Messages) {
                        trigger_error('Failed to unserialize flash messages', E_USER_WARNING);
                    }

                    $flashMessages = $flashMessages->array();
                }
            }

            $displayErrors = $this->app->isDebug() ?
                $this->app->errors()->all() :
                $this->app->errors()->triggered()->array();

            $template->assign("flashMessages", $flashMessages ?? []);
            $template->assign("errors", $displayErrors);
            $template->assign("remote", $this->remote());

            if ($this->page) {
                $template->assign("page", $this->page->array());
            }

            $template->assign("config", [
                "public" => $this->app->config()->public()->array(),
            ]);

            // Default response type (despite of ACCEPT header)
            $this->response()->header("content-type", "text/html");

            // Populate Response "body" param
            $this->response()->body($template->knit());
        } catch (KnitException $e) {
            throw $e;
        }
    }
}
