<?php
declare(strict_types=1);

require "../vendor/autoload.php";

var_dump("Admin");

$kernel = \App\Common\Kernel::Bootstrap();
var_dump($kernel->dirs()->root()->path());
var_dump($kernel->dirs()->root()->permissions());

try {
    var_dump($kernel->dirs()->storage()->path());
    var_dump($kernel->dirs()->storage()->permissions());
} catch (Exception $e) {
    var_dump(get_class($e));
    var_dump($e->getMessage());
    var_dump($e->getTraceAsString());
}

try {
    var_dump($kernel->dirs()->log()->path());
    var_dump($kernel->dirs()->log()->permissions());
} catch (Exception $e) {
    var_dump(get_class($e));
    var_dump($e->getMessage());
    var_dump($e->getTraceAsString());
}

try {
    var_dump($kernel->dirs()->config()->path());
    var_dump($kernel->dirs()->config()->permissions());
} catch (Exception $e) {
    var_dump(get_class($e));
    var_dump($e->getMessage());
    var_dump($e->getTraceAsString());
}
