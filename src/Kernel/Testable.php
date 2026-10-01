<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Kernel;

use OpenDxp;
use OpenDxp\Bundle\CustomReportsBundle\OpenDxpCustomReportsBundle;
use OpenDxp\Bundle\StaticRoutesBundle\OpenDxpStaticRoutesBundle;
use OpenDxp\HttpKernel\BundleCollection\BundleCollection;
use OpenDxp\TestFoundation\Kernel\CompilerPass\DisableCsrfProtection;
use OpenDxp\TestFoundation\Kernel\CompilerPass\MakeServicesPublic;
use ReflectionClass;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use DAMA\DoctrineTestBundle\DAMADoctrineTestBundle;
use Playwright\Symfony\PlaywrightSymfonyBundle;
use Zenstruck\Foundry\ZenstruckFoundryBundle;

trait Testable
{
    /**
     * Configuring a bundle that is absent stops the container from building, so each of these is
     * loaded only when its bundle is registered.
     */
    private const array CONFIGURED_WHEN_PRESENT = [
        OpenDxpStaticRoutesBundle::class => 'static-routes.yaml',
        OpenDxpCustomReportsBundle::class => 'custom-reports.yaml',
    ];

    /**
     * @return class-string
     */
    abstract protected function getServicesClass(): string;

    protected function registerCoreBundlesToCollection(BundleCollection $collection): void
    {
        parent::registerCoreBundlesToCollection($collection);

        $collection->addBundle(new ZenstruckFoundryBundle());
        $collection->addBundle(new DAMADoctrineTestBundle());
        $collection->addBundle(new PlaywrightSymfonyBundle());
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $package = dirname(__DIR__, 2) . '/config';

        $loader->load($package . '/testing.yaml');

        $registered = array_map(static fn (object $bundle): string => $bundle::class, $this->getBundles());

        foreach (self::CONFIGURED_WHEN_PRESENT as $bundle => $file) {
            if (in_array($bundle, $registered, true)) {
                $loader->load($package . '/testing/' . $file);
            }
        }

        $beside = dirname((new ReflectionClass(static::class))->getFileName());
        $state = sprintf('%s/config/%s.yaml', $beside, $this->getEnvironment());

        if (is_file($state)) {
            $loader->load($state);
        }
    }

    protected function getContainerClass(): string
    {
        return 'TestContainer' . ($this->debug ? 'Debug' : '');
    }

    public function boot(): void
    {
        OpenDxp::setKernel($this);

        parent::boot();
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DisableCsrfProtection());

        $container->addCompilerPass(
            new MakeServicesPublic((new ReflectionClass($this->getServicesClass()))->getNamespaceName()),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            -100000,
        );
    }
}
