<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\Document;
use OpenDxp\Model\Site;

/**
 * @extends AbstractSavingFactory<Site>
 */
final class SiteFactory extends AbstractSavingFactory
{
    public static function class(): string
    {
        return Site::class;
    }

    public function rootedAt(Document $root): static
    {
        return $this->with(['rootId' => $root->getId()]);
    }

    /**
     * The domains that reach this site besides its main one.
     *
     * @param list<string> $domains
     */
    public function alsoAt(array $domains): static
    {
        return $this->with(['domains' => $domains]);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function settings(array $settings): static
    {
        return $this->with(['customSettings' => $settings]);
    }

    protected function defaults(): array
    {
        return [
            'mainDomain' => sprintf('site-%s.test', self::faker()->unique()->numerify('##########')),
        ];
    }

    protected function initialize(): static
    {
        return parent::initialize()->beforeInstantiate(
            static function (array $parameters): array {
                $parameters['rootId'] ??= PageFactory::createOne([
                    'key' => str_replace('.', '-', $parameters['mainDomain']),
                ])->getId();

                return $parameters;
            },
        );
    }
}
