<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Kernel\CompilerPass;

use OpenDxp\TestFoundation\Controller\DefaultController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class UseFoundationControllerWithoutApplication implements CompilerPassInterface
{
    private const string PARAMETER = 'opendxp.documents.default_controller';

    /**
     * Sets the default controller of the foundation when the configured one does not exist.
     *
     * The test application of a bundle has no default controller. A project renders its pages through its own.
     */
    public function process(ContainerBuilder $container): void
    {
        $class = explode('::', $container->getParameter(self::PARAMETER))[0];

        if (class_exists($class) || $container->has($class)) {
            return;
        }

        $container->setParameter(self::PARAMETER, DefaultController::class . '::defaultAction');
    }
}
