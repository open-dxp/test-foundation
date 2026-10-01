<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Kernel;

use OpenDxp\Kernel;

/**
 * A bundle ships no application, so this builds one around it. A project has its own kernel and
 * applies {@see Testable} to it instead.
 */
abstract class TestKernel extends Kernel
{
    use Testable;
}
