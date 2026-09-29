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

    /**
     * Makes this document the language variant of another one.
     *
     * OpenDXP keeps the variants of a document in a table of their own, always pointing at one
     * source, so linking a third document to either of two already linked ones joins the same
     * set. The language is the one the document carries, which `inLocale()` puts there.
     *
     * The method lives on the service's Dao and is reached through a magic call, so it is wrapped
     * here rather than written out in every test.
     */
    public function translationOf(Document $source, ?string $locale = null): static
    {
        return $this->afterInstantiate(
            static fn (Document $document) => (new Document\Service())
                ->addTranslation($source, $document, $locale),
            self::LINK,
        );
    }

    public function unpublished(): static
    {
        return $this->with(['published' => false]);
    }

    /**
     * The language a document belongs to. OpenDXP keeps it in an inheritable property rather
     * than a field, which is why this is a hook and not an attribute.
     */
    public function inLocale(string $locale): static
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
