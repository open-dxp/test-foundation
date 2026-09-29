<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

/**
 * Starts OpenDXP for a test run, in any application that requires this package.
 *
 * The root is taken from the autoloader rather than from this file: composer may install a
 * package as a symlink, and then __DIR__ points at the checkout instead of the application.
 * vendor/composer/ClassLoader.php is never a symlink, so it always names the real root.
 */
$loader = (new ReflectionClass(ClassLoader::class))->getFileName();

defined('OPENDXP_PROJECT_ROOT') || define('OPENDXP_PROJECT_ROOT', dirname($loader, 3));

require OPENDXP_PROJECT_ROOT . '/vendor/autoload.php';

if (is_file(OPENDXP_PROJECT_ROOT . '/.env')) {
    (new Symfony\Component\Dotenv\Dotenv())->loadEnv(OPENDXP_PROJECT_ROOT . '/.env');
}

OpenDxp\Bootstrap::setProjectRoot();
OpenDxp\Bootstrap::bootstrap();
