<?php
declare(strict_types=1);

var_dump("Admin");

$kernel = \App\Common\Kernel::Bootstrap();
var_dump($kernel->dirs()->root()->path());
var_dump($kernel->dirs()->root()->permissions());
var_dump($kernel->dirs()->storage()->path());
var_dump($kernel->dirs()->storage()->permissions());
var_dump($kernel->dirs()->log()->path());
var_dump($kernel->dirs()->log()->permissions());
var_dump($kernel->dirs()->config()->path());
var_dump($kernel->dirs()->config()->permissions());
