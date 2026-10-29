<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\TestCase;

use OpenDxp\TestFoundation\EnvironmentTestCase;

/**
 * Configures the domain environment.test.
 */
abstract class DomainTestCase extends EnvironmentTestCase
{
    protected static function environment(): string
    {
        return 'domain';
    }
}
