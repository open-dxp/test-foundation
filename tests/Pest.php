<?php

declare(strict_types=1);

use OpenDxp\TestFoundation\BrowserTestCase;
use OpenDxp\TestFoundation\TestCase;

pest()->extend(TestCase::class)->in('Feature');

pest()->extend(BrowserTestCase::class)->group('browser')->in('Browser');
