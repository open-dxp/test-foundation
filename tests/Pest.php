<?php

declare(strict_types=1);

use OpenDxp\TestFoundation\BrowserTestCase;
use OpenDxp\TestFoundation\TestCase;
use OpenDxp\TestFoundation\Tests\TestCase\DomainTestCase;
use OpenDxp\TestFoundation\WithoutRollbackTestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(DomainTestCase::class)->in('Environment');
pest()->extend(WithoutRollbackTestCase::class)->in('WithoutRollback');

pest()->extend(BrowserTestCase::class)->group('browser')->in('Browser');
