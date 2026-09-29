<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\Element\ElementInterface;

/**
 * What every document, asset and object in OpenDXP has in common: a place in a tree and an owner.
 *
 * @template T of ElementInterface
 *
 * @extends AbstractSavingFactory<T>
 */
abstract class AbstractElementFactory extends AbstractSavingFactory
{
    public function childOf(ElementInterface $parent): static
    {
        return $this->with(['parentId' => $parent->getId()]);
    }

    protected function defaults(): array
    {
        return [
            'parentId'         => 1,
            'userOwner'        => 1,
            'userModification' => 1,
        ];
    }
}
