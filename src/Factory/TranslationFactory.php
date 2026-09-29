<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\Translation;

/**
 * @extends AbstractSavingFactory<Translation>
 */
final class TranslationFactory extends AbstractSavingFactory
{
    public static function class(): string
    {
        return Translation::class;
    }

    /**
     * @param array<string, string> $translations one per language
     */
    public function saying(array $translations): static
    {
        return $this->with(['translations' => $translations]);
    }

    public function forAdmin(): static
    {
        return $this->with(['domain' => Translation::DOMAIN_ADMIN]);
    }

    protected function defaults(): array
    {
        return [
            'key'    => sprintf('key.%s', self::faker()->unique()->numerify('##########')),
            'domain' => Translation::DOMAIN_DEFAULT,
        ];
    }
}
