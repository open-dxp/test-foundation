<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\Document;
use OpenDxp\Model\Document\Link;

/**
 * @extends AbstractDocumentFactory<Link>
 */
final class LinkFactory extends AbstractDocumentFactory
{
    public static function class(): string
    {
        return Link::class;
    }

    /**
     * A link has no meaning without something it points at,
     * so this is the one state a caller always sets.
     */
    public function to(Document $target): static
    {
        return $this->with(['internal' => $target->getId()]);
    }

    protected function defaults(): array
    {
        return [
            ...parent::defaults(),
            'key'          => sprintf('link-%s', self::faker()->unique()->numerify('##########')),
            'type'         => 'link',
            'linktype'     => 'internal',
            'internalType' => 'document',
        ];
    }

    protected function initialize(): static
    {
        return parent::initialize()->inNavigation();
    }
}
