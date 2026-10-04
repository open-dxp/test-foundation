<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Kernel;

use OpenDxp;
use OpenDxp\Bundle\CustomReportsBundle\OpenDxpCustomReportsBundle;
use OpenDxp\Bundle\StaticRoutesBundle\OpenDxpStaticRoutesBundle;
use OpenDxp\HttpKernel\BundleCollection\BundleCollection;
use OpenDxp\TestFoundation\Kernel\CompilerPass\DisableCsrfProtection;
use OpenDxp\TestFoundation\Kernel\CompilerPass\MakeServicesPublic;
use OpenDxp\TestFoundation\GeoIp;
use Composer\InstalledVersions;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileExistenceResource;
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

    protected function registerCoreBundlesToCollection(BundleCollection $collection): void
    {
        parent::registerCoreBundlesToCollection($collection);

        $collection->addBundle(new ZenstruckFoundryBundle());
        $collection->addBundle(new DAMADoctrineTestBundle());
        $collection->addBundle(new PlaywrightSymfonyBundle());
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        // Set before the application's configuration, so that a bundle or project naming its own database wins.
        $loader->load(static function (ContainerBuilder $container): void {
            $container->setParameter('opendxp.geoip.db_file', realpath(GeoIp::DATABASE));
        });

        $beside = dirname((new ReflectionClass(static::class))->getFileName());

        // Symfony accepts new firewalls only from the first file that names any. A bundle that needs a firewall of its
        // own, like a frontend login, therefore lists all firewalls in this file, and it is loaded before the
        // application's.
        $security = $beside . '/config/security.yaml';

        $loader->load(static function (ContainerBuilder $container) use ($security): void {
            $container->addResource(new FileExistenceResource($security));
        });

        if (is_file($security)) {
            $loader->load($security);
        }

        parent::registerContainerConfiguration($loader);

        $package = dirname(__DIR__, 2) . '/config';

        $loader->load($package . '/testing.yaml');

        $registered = array_map(static fn (object $bundle): string => $bundle::class, $this->getBundles());

        foreach (self::CONFIGURED_WHEN_PRESENT as $bundle => $file) {
            if (in_array($bundle, $registered, true)) {
                $loader->load($package . '/testing/' . $file);
            }
        }

        $environment = sprintf('%s/config/%s.yaml', $beside, $this->getEnvironment());

        // Without the resource, a configuration added after the first run would be ignored until the cache is cleared.
        $loader->load(static function (ContainerBuilder $container) use ($environment): void {
            $container->addResource(new FileExistenceResource($environment));
        });

        if (is_file($environment)) {
            $loader->load($environment);
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
            new MakeServicesPublic($this->directoriesUnderTest()),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            -100000,
        );
    }

    /**
     * @return list<string>
     */
    private function directoriesUnderTest(): array
    {
        $tests = realpath($this->getProjectDir() . '/tests');

        return $tests === false ? [$this->packageUnderTest()] : [$this->packageUnderTest(), $tests];
    }

    /**
     * A bundle is tested inside an application that `opendxp-test bundle` builds and names it in. A project is the
     * application.
     */
    private function packageUnderTest(): string
    {
        $manifest = json_decode((string) file_get_contents($this->getProjectDir() . '/composer.json'), true);
        $package = $manifest['extra']['opendxp-test']['package'] ?? null;

        $directory = $package === null
            ? $this->getProjectDir()
            : InstalledVersions::getInstallPath($package);

        return realpath((string) $directory)
            ?: throw new RuntimeException(sprintf('The package under test has no directory at %s.', $directory));
    }
}
