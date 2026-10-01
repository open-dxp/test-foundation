<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Kernel;

use OpenDxp\Kernel;

abstract class TestKernel extends Kernel
{
    use Testable;
}
