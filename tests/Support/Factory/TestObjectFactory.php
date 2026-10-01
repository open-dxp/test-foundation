<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Support\Factory;

use OpenDxp\Model\DataObject\TestObject;
use OpenDxp\TestFoundation\Factory\AbstractDataObjectFactory;

/**
 * @extends AbstractDataObjectFactory<TestObject>
 */
final class TestObjectFactory extends AbstractDataObjectFactory
{
    public static function class(): string
    {
        return TestObject::class;
    }
}
