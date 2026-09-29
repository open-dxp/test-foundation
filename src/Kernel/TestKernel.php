<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Kernel;

use OpenDxp\Kernel;

/**
 * The kernel a bundle's tests run against.
 *
 * A bundle ships no application, so this builds one around it: OpenDXP's own kernel with whatever
 * the bundle registers on top. A project has its own kernel and applies {@see Testable} to it
 * instead.
 */
abstract class TestKernel extends Kernel
{
    use Testable;
}
