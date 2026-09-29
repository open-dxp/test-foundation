<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Kernel\CompilerPass;

use OpenDxp\Bundle\AdminBundle\EventListener\CsrfProtectionListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class DisableCsrfProtection implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->removeDefinition(CsrfProtectionListener::class);
    }
}
