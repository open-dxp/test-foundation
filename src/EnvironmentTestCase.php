<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use Symfony\Component\HttpKernel\KernelInterface;

abstract class EnvironmentTestCase extends TestCase
{
    abstract protected static function environment(): string;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['environment' => static::environment()] + $options);
    }
}
