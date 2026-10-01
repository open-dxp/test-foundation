<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use OpenDxp\Model\User;
use OpenDxp\Security\User\User as SecurityUser;
use Closure;
use Symfony\Bundle\FrameworkBundle\KernelBrowser as Client;
use Zenstruck\Browser\KernelBrowser;
use Zenstruck\Browser\PlaywrightBrowser;

final class Browser
{
    private const string ADMIN_FIREWALL = 'opendxp_admin';

    private static ?Closure $playwright = null;

    private static ?Client $client = null;

    public static function visit(string $uri): KernelBrowser
    {
        return self::start()->visit($uri);
    }

    public static function actingAs(User $user): KernelBrowser
    {
        return self::start()->actingAs(new SecurityUser($user), self::ADMIN_FIREWALL);
    }

    public static function playwrightActingAs(User $user): PlaywrightBrowser
    {
        return self::playwright()->actingAs(new SecurityUser($user), self::ADMIN_FIREWALL);
    }

    /**
     * @internal the browser test case hands its factory over when it starts
     */
    public static function setPlaywrightFactory(Closure $factory): void
    {
        self::$playwright = $factory;
    }

    public static function playwright(): PlaywrightBrowser
    {
        return (self::$playwright ?? throw new \LogicException(
            'No browser available. A test driving one must extend ' . BrowserTestCase::class . '.'
        ))();
    }

    /**
     * @internal the test case gives every test a browser of its own
     */
    public static function reset(): void
    {
        self::$client = null;
    }

    public static function start(): KernelBrowser
    {
        // Otherwise the kernel keeps the test case's request as the main one.
        Container::requestStack()->pop();

        // Symfony hands out a new client for every call, and only a client that has already sent
        // something reboots the kernel between requests.
        self::$client ??= Container::testClient();

        return new KernelBrowser(self::$client);
    }
}
