<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Fixtures;

use OpenDxp\Model\DataObject\TestObject;
use OpenDxp\TestFoundation\Factory\AbstractDataObjectFactory;

/**
 * What a bundle writes for a class it defines itself. The class does not exist until the
 * definition beside this file has been installed.
 *
 * @extends AbstractDataObjectFactory<TestObject>
 */
final class TestObjectFactory extends AbstractDataObjectFactory
{
    public static function class(): string
    {
        return TestObject::class;
    }
}
