<?php

declare(strict_types=1);

use OpenDxp\TestFoundation\TestCase;
use Zenstruck\Foundry\Test\Factories;

// Everything here needs a running application, and every factory is built through Foundry.
pest()->extend(TestCase::class)->use(Factories::class)->in('.');
