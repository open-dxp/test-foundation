<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\PHPUnit;

use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;

trait BootKernelTrait
{
    private static function bootKernel(): KernelInterface
    {
        $class = self::kernelClass();

        $kernel = new $class('test', true);
        $kernel->boot();

        return $kernel;
    }

    /**
     * @return class-string<KernelInterface>
     *
     * @psalm-taint-escape callable
     */
    private static function kernelClass(): string
    {
        $class = $_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS'] ?? getenv('KERNEL_CLASS');

        if (!is_string($class) || !class_exists($class)) {
            throw new RuntimeException('KERNEL_CLASS names no kernel. Set it in the <php> section of phpunit.xml.');
        }

        if (!is_a($class, KernelInterface::class, true)) {
            throw new RuntimeException(sprintf('%s is no kernel.', $class));
        }

        return $class;
    }
}
