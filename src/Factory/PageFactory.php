<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\Document\Page;

/**
 * @extends AbstractPageSnippetFactory<Page>
 */
final class PageFactory extends AbstractPageSnippetFactory
{
    public static function class(): string
    {
        return Page::class;
    }

    protected function defaults(): array
    {
        return [
            ...parent::defaults(),
            'key' => sprintf('page-%s', self::faker()->unique()->numerify('##########')),
        ];
    }
}
