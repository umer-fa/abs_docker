<?php
declare(strict_types=1);

require "../vendor/autoload.php";

$kernel = \App\Common\Kernel::Bootstrap();

trigger_error('Testing error handler 1', E_NOTICE);
sleep(2);
trigger_error('Testing error handler 2', E_USER_WARNING);
sleep(2);
$kernel->errorHandler()->setTraceLevel(E_USER_WARNING);
trigger_error('Testing error handler 3', E_USER_WARNING);

test();

function test()
{
    throw new RuntimeException('Finished');
}
