<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A test case that boots the application in one named state.
 */
abstract class StateTestCase extends TestCase
{
    abstract protected static function state(): string;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['environment' => static::state()] + $options);
    }
}
