<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\DataObject\Folder;

/**
 * @extends AbstractElementFactory<Folder>
 */
final class ObjectFolderFactory extends AbstractElementFactory
{
    public static function class(): string
    {
        return Folder::class;
    }

    protected function defaults(): array
    {
        return [
            ...parent::defaults(),
            'key'  => sprintf('object-folder-%s', self::faker()->unique()->numerify('##########')),
            'type' => 'folder',
        ];
    }
}
