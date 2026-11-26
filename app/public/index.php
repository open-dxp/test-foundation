<?php

declare(strict_types=1);

use OpenDxp\Bootstrap;
use OpenDxp\TestFoundation\Application\TestApplication;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

// Like the console of the tests, a request runs the kernel of the tests in this application.
// A web server that names either of them wins.
$_SERVER['OPENDXP_PROJECT_ROOT'] ??= dirname(__DIR__);
$_SERVER['OPENDXP_KERNEL_CLASS'] ??= TestApplication::readKernelClass(dirname(__DIR__));

Bootstrap::setProjectRoot();

return static function () {
    Bootstrap::bootstrap();

    return Bootstrap::kernel();
};
