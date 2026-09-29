<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\Asset\Image;

/**
 * @extends AbstractElementFactory<Image>
 */
final class ImageAssetFactory extends AbstractElementFactory
{
    public static function class(): string
    {
        return Image::class;
    }

    /**
     * The picture every image starts out as, unless a test hands over its own bytes.
     */
    public static function fixture(): string
    {
        return dirname(__DIR__, 2) . '/fixtures/image.jpg';
    }

    protected function defaults(): array
    {
        return [
            ...parent::defaults(),
            'type'     => 'image',
            'filename' => sprintf('image-%s.jpg', self::faker()->unique()->numerify('##########')),
            'data'     => file_get_contents(self::fixture()),
        ];
    }
}
