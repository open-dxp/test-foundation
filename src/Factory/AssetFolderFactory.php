<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\Asset\Folder;

/**
 * @extends AbstractElementFactory<Folder>
 */
final class AssetFolderFactory extends AbstractElementFactory
{
    public static function class(): string
    {
        return Folder::class;
    }

    protected function defaults(): array
    {
        return [
            ...parent::defaults(),
            'filename' => sprintf('asset-folder-%s', self::faker()->unique()->numerify('##########')),
            'type'     => 'folder',
        ];
    }
}
