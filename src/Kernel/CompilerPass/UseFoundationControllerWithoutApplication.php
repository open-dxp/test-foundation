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
     * The test application of a bundle has no default controller of its own, while a project renders its pages through its own.
     * The controller of the foundation therefore only steps in when the configured one does not exist.
     */
    public function process(ContainerBuilder $container): void
    {
        $controller = $container->getParameter(self::PARAMETER);
        $class = is_string($controller) ? explode('::', $controller)[0] : '';

        if (class_exists($class) || $container->has($class)) {
            return;
        }

        $container->setParameter(self::PARAMETER, DefaultController::class . '::defaultAction');
    }
}
