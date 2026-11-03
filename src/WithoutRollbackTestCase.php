<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;

#[SkipDatabaseRollback]
abstract class WithoutRollbackTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DatabaseSnapshot::save();
    }

    protected function tearDown(): void
    {
        DatabaseSnapshot::restore();

        parent::tearDown();
    }
}
