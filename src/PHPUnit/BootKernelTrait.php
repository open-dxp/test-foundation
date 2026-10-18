<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\PHPUnit;

use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;

trait BootKernelTrait
{
    private static function bootKernel(): KernelInterface
    {
        $class = $_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS'] ?? getenv('KERNEL_CLASS');

        if (!is_string($class) || !class_exists($class)) {
            throw new RuntimeException('KERNEL_CLASS names no kernel. Set it in the <php> section of phpunit.xml.');
        }

        $kernel = new $class('test', true);

        if (!$kernel instanceof KernelInterface) {
            throw new RuntimeException(sprintf('%s is no kernel.', $class));
        }

        $kernel->boot();

        return $kernel;
    }
}
