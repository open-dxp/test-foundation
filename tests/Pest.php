<?php

declare(strict_types=1);

use OpenDxp\TestFoundation\BrowserTestCase;
use OpenDxp\TestFoundation\TestCase;
use OpenDxp\TestFoundation\Tests\TestCase\DomainTestCase;
use OpenDxp\TestFoundation\Tests\TestCase\GermanRequestTestCase;
use OpenDxp\TestFoundation\WithoutRollbackTestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(DomainTestCase::class)->in('Environment');
pest()->extend(GermanRequestTestCase::class)->in('Request');
pest()->extend(WithoutRollbackTestCase::class)->in('WithoutRollback');

pest()->extend(BrowserTestCase::class)->group('browser')->in('Browser');
