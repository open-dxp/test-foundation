<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\Document\Snippet;

/**
 * @extends AbstractPageSnippetFactory<Snippet>
 */
final class SnippetFactory extends AbstractPageSnippetFactory
{
    public static function class(): string
    {
        return Snippet::class;
    }

    protected function defaults(): array
    {
        return [
            ...parent::defaults(),
            'key'  => sprintf('snippet-%s', self::faker()->unique()->numerify('##########')),
            'type' => 'snippet',
        ];
    }
}
