<?php
declare(strict_types=1);

require "../vendor/autoload.php";

try {
    $kernel = \App\API\APIService::Bootstrap();
    $errorHandler = new \App\Common\Kernel\ErrorHandler\HttpErrorHandler($kernel);
    $kernel->setErrorHandler($errorHandler);
    $router = $kernel->router();

    $webHooksRoute = $router->route('/wh/*', 'App\API\Controllers\WebHooks\*')
        ->fallbackController('App\API\Controllers\Hello');

    $defaultRoute = $router->route('/*', 'App\API\Controllers\*')
        ->fallbackController('App\API\Controllers\Hello');

    \Comely\Http\RESTful::Request($router, function (\Comely\Http\Router\AbstractController $page) {
        $page->router()->response()->send($page);
    });
} catch (Exception $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
