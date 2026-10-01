<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\DataObject\Concrete;

/**
 * There is no DataObjectFactory, because the PHP class behind a data object is generated from a
 * class definition and exists only once the package that defines it has installed it.
 *
 * @template T of Concrete
 *
 * @extends AbstractElementFactory<T>
 */
abstract class AbstractDataObjectFactory extends AbstractElementFactory
{
    public function unpublished(): static
    {
        return $this->with(['published' => false]);
    }

    protected function defaults(): array
    {
        return [
            ...parent::defaults(),
            'key'       => sprintf('object-%s', self::faker()->unique()->numerify('##########')),
            'published' => true,
        ];
    }
}
