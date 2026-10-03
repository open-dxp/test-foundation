<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Kernel\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final readonly class MakeServicesPublic implements CompilerPassInterface
{
    /**
     * @param list<string> $directories
     */
    public function __construct(private array $directories)
    {
    }

    /**
     * Symfony removes a private service that nothing injects, and the services of a package are rarely injected in its
     * test application. Those whose class lies in one of the directories are made public, so a test can still fetch them.
     */
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            if ($this->isUnderTest($container, $definition)) {
                $definition->setPublic(true);
            }
        }

        foreach ($container->getAliases() as $alias) {
            $target = (string) $alias;

            if ($container->hasDefinition($target) && $this->isUnderTest($container, $container->getDefinition($target))) {
                $alias->setPublic(true);
            }
        }
    }

    private function isUnderTest(ContainerBuilder $container, Definition $definition): bool
    {
        if ($definition->isAbstract() || $definition->isSynthetic()) {
            return false;
        }

        $class = $container->getParameterBag()->resolveValue($definition->getClass());
        $file = is_string($class) ? $container->getReflectionClass($class, false)?->getFileName() : null;
        $file = is_string($file) ? realpath($file) : false;

        if ($file === false) {
            return false;
        }

        foreach ($this->directories as $directory) {
            // A project is the package under test, and its vendor directory holds every other package.
            if (str_starts_with($file, $directory . '/') && !str_starts_with($file, $directory . '/vendor/')) {
                return true;
            }
        }

        return false;
    }
}
