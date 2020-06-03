<?php
declare(strict_types=1);

require "../vendor/autoload.php";

try {
    $kernel = \App\Admin\AppAdmin::Bootstrap();
    $errorHandler = new \App\Common\Kernel\ErrorHandler\HttpErrorHandler($kernel);
    $kernel->setErrorHandler($errorHandler);
    $router = $kernel->router();

    $authRoute = $router->route('/login', 'App\Admin\Controllers\Login');
    $defaultRoute = $router->route('/*', 'App\Admin\Controllers\*')
        ->ignorePathIndexes(0)
        ->fallbackController('App\Admin\Controllers\Dashboard');

    $adminAuth = $kernel->config()->adminHttpAuth();
    if ($adminAuth) {
        $auth = new \Comely\Http\Router\Authentication\BasicAuth("Administration Panel");
        $auth->user($adminAuth[0], $adminAuth[1]);

        // Apply HTTP basic authentication to routes
        $defaultRoute->auth($auth);
        $authRoute->auth($auth);
    }

    \Comely\Http\RESTful::Request($router, function (\Comely\Http\Router\AbstractController $page) {
        $page->router()->response()->send($page);
    });
} catch (Exception $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
