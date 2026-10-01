<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use Closure;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class Container
{
    /**
     * @var Closure(): ContainerInterface|null
     */
    private static ?Closure $container = null;

    /**
     * The container is asked for on every call rather than kept, because a request through the
     * browser reboots the kernel and builds a new one.
     *
     * @internal called by the test case once the kernel is up
     *
     * @param Closure(): ContainerInterface $container
     */
    public static function setFactory(Closure $container): void
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
        return (self::$container ?? throw new \LogicException(
            'No application is running. A test reaching for a service must extend a test case of '
            . 'this package, which hands the container over when it boots.'
        ))();
    }
}
