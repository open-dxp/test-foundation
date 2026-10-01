<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\Document;

/**
 * @template T of Document
 *
 * @extends AbstractElementFactory<T>
 */
abstract class AbstractDocumentFactory extends AbstractElementFactory
{
    /**
     * Linking runs after the write, because both documents need an id first.
     */
    private const int LINK = -2000;

    public function withTranslationOf(Document $source, ?string $locale = null): static
    {
        return $this->afterInstantiate(
            static fn (Document $document) => (new Document\Service())
                ->addTranslation($source, $document, $locale),
            self::LINK,
        );
    }

    /**
     * Without a navigation name OpenDXP leaves a document out of every menu.
     */
    public function withNavigationName(?string $name = null): static
    {
        return $this->afterInstantiate(
            static function (Document $document) use ($name): void {
                $title = $name ?? $document->getKey();

                $document->setProperty('navigation_title', 'text', $title);
                $document->setProperty('navigation_name', 'text', $title);
            },
        );
    }

    public function unpublished(): static
    {
        return $this->with(['published' => false]);
    }

    /**
     * OpenDXP keeps the language in an inheritable property rather than a field, which is why
     * this is a hook and not an attribute.
     */
    public function withLocale(string $locale): static
    {
        return $this->afterInstantiate(
            static fn (Document $document) => $document->setProperty('language', 'text', $locale, false, true),
        );
    }

    protected function defaults(): array
    {
        return [
            ...parent::defaults(),
            'published' => true,
        ];
    }
}
