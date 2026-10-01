<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\Document\PageSnippet;

/**
 * The documents OpenDXP renders through a controller.
 *
 * Nothing here sets one. A document that carries no controller falls back to the application's
 * `opendxp.documents.default_controller`, which is where an application states what renders its
 * documents, so a test only speaks up when it wants something else.
 *
 * @template T of PageSnippet
 *
 * @extends AbstractDocumentFactory<T>
 */
abstract class AbstractPageSnippetFactory extends AbstractDocumentFactory
{
    /**
     * @param class-string $controller
     */
    public function controller(string $controller, string $action): static
    {
        return $this->with(['controller' => sprintf('%s::%s', $controller, $action)]);
    }

    protected function defaults(): array
    {
        return [
            ...parent::defaults(),
            // A document under test is not the place to find out that an editable is missing.
            'missingRequiredEditable' => false,
        ];
    }

    protected function initialize(): static
    {
        return parent::initialize()->inNavigation();
    }
}
