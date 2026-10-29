<?php

declare(strict_types=1);

use OpenDxp\TestFoundation\BrowserTestCase;
use OpenDxp\TestFoundation\TestCase;
use OpenDxp\TestFoundation\Tests\TestCase\DomainTestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(DomainTestCase::class)->in('Environment');

pest()->extend(BrowserTestCase::class)->group('browser')->in('Browser');
