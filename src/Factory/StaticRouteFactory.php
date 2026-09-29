<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Bundle\StaticRoutesBundle\Model\Staticroute;

/**
 * @extends AbstractSavingFactory<Staticroute>
 */
final class StaticRouteFactory extends AbstractSavingFactory
{
    public static function class(): string
    {
        return Staticroute::class;
    }

    /**
     * @param class-string $controller
     */
    public function controller(string $controller, string $action): static
    {
        return $this->with(['controller' => sprintf('%s::%s', $controller, $action)]);
    }

    public function at(string $pattern, string $reverse): static
    {
        return $this->with(['pattern' => $pattern, 'reverse' => $reverse]);
    }

    protected function defaults(): array
    {
        return [
            'name'     => sprintf('route_%s', self::faker()->unique()->numerify('##########')),
            'priority' => 0,
            'siteId'   => [],
        ];
    }
}
