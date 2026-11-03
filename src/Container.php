<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use Closure;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class Container
{
    /**
     * @var Closure(): ContainerInterface|null
     */
    private static ?Closure $container = null;

    /**
     * Sets the factory that returns the container. A request through the browser reboots the kernel and builds a new
     * container, so the factory is called every time.
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
     * @param class-string<T>|string $service a class or the id of a service
     *
     * @return ($service is class-string<T> ? T : object)
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
        // Otherwise the kernel keeps the request of the test case as the main one.
        self::requestStack()->pop();

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
        return (self::$container ?? throw new LogicException(
            'No application is running. A test reaching for a service must extend a test case of '
            . 'this package, which hands the container over when it boots.'
        ))();
    }
}
