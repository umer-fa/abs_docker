<?php
declare(strict_types=1);

chdir(__DIR__); // Change PHP current working directory
require "../vendor/autoload.php";

// Prepare Arguments
$args = $argv[1] ?? "";
$args = explode(";", substr($args, 1, -1));

// Instantiate CLI
$bin = new \Comely\Filesystem\Directory(__DIR__ . DIRECTORY_SEPARATOR . "scripts");
$cli = new \Comely\CLI\CLI($bin, $args);

// Listen to events, etc...
$cli->events()->beforeExec();

// Execute
$cli->exec();