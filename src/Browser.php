<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use OpenDxp\Model\User;
use OpenDxp\Security\User\User as SecurityUser;
use Closure;
use Zenstruck\Browser\KernelBrowser;
use Zenstruck\Browser\PlaywrightBrowser;

final class Browser
{
    private const string ADMIN_FIREWALL = 'opendxp_admin';

    private static ?Closure $playwright = null;

    public static function visit(string $uri): KernelBrowser
    {
        return self::start()->visit($uri);
    }

    public static function as(User $user): KernelBrowser
    {
        return self::start()->actingAs(new SecurityUser($user), self::ADMIN_FIREWALL);
    }

    public static function playwrightAs(User $user): PlaywrightBrowser
    {
        return self::playwright()->actingAs(new SecurityUser($user), self::ADMIN_FIREWALL);
    }

    /**
     * @internal the browser test case hands its factory over when it starts
     */
    public static function providedBy(Closure $factory): void
    {
        self::$playwright = $factory;
    }

    /**
     * A real browser, for what only a browser can do: run the JavaScript of a page and act on it.
     */
    public static function playwright(): PlaywrightBrowser
    {
        return (self::$playwright ?? throw new \LogicException(
            'No browser available. A test driving one must extend ' . BrowserTestCase::class . '.'
        ))();
    }

    /**
     * A browser that has not gone anywhere yet, for a request that is not a plain page visit.
     */
    public static function start(): KernelBrowser
    {
        return new KernelBrowser(Container::testClient());
    }
}
