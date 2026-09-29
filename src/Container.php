<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

/**
 * Reaches a service of the running application.
 *
 * A Pest test body is a closure, so `self::getContainer()` inside it resolves at runtime but is
 * invisible to static analysis and to an editor. The test case hands the container over here when
 * it boots, which keeps every call in a test typed and navigable.
 */
final class Container
{
    private static ?ContainerInterface $container = null;

    /**
     * @internal called by the test case once the kernel is up
     */
    public static function of(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $service
     *
     * @return T
     */
    public static function get(string $service): object
    {
        return self::container()->get($service);
    }

    public static function requestStack(): RequestStack
    {
        return self::container()->get('request_stack');
    }

    public static function sessionFactory(): SessionFactoryInterface
    {
        return self::container()->get('session.factory');
    }

    public static function testClient(): KernelBrowser
    {
        return self::container()->get('test.client');
    }

    public static function parameter(string $name): mixed
    {
        return self::container()->getParameter($name);
    }

    public static function environment(): string
    {
        return self::parameter('kernel.environment');
    }

    private static function container(): ContainerInterface
    {
        return self::$container ?? throw new \LogicException(
            'No application is running. A test reaching for a service must extend a test case of '
            . 'this package, which hands the container over when it boots.'
        );
    }
}
