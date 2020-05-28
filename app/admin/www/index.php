<?php
declare(strict_types=1);

require "../vendor/autoload.php";

$kernel = \App\Common\Kernel::Bootstrap();

trigger_error('Testing error handler', E_USER_NOTICE);
sleep(2);
trigger_error('Testing error handler', E_USER_WARNING);
sleep(2);
$kernel->setDebug(true);
trigger_error('Testing error handler 3', E_USER_WARNING);

throw new RuntimeException('Finished');
