<?php
declare(strict_types=1);

chdir(__DIR__); // Change PHP current working directory
require "../vendor/autoload.php";

try {
    $kernel = \App\Common\Kernel::Bootstrap();

    // Prepare Arguments
    $args = $argv[1] ?? "";
    $args = explode(";", substr($args, 1, -1));

    // Instantiate CLI
    $bin = new \Comely\Filesystem\Directory(__DIR__ . DIRECTORY_SEPARATOR . "scripts");
    $cli = new \App\Common\Kernel\CLI($kernel, $bin, $args);

    // Listen to events, etc...
    $cli->events()->beforeExec();

    // Execute
    $cli->exec();
} catch (Exception $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
