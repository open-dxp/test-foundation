<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Kernel\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

final readonly class MakeServicesPublic implements CompilerPassInterface
{
    public function __construct(private string $namespace)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getServiceIds() as $serviceId) {
            if (!str_starts_with($serviceId, $this->namespace)) {
                continue;
            }

            if ($container->hasAlias($serviceId)) {
                $container->getAlias($serviceId)->setPublic(true);
            }

            try {
                $container->findDefinition($serviceId)->setPublic(true);
            } catch (ServiceNotFoundException) {
                // An alias may point outside the container, and getServiceIds lists it anyway.
                continue;
            }
        }
    }
}
