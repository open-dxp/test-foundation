<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use OpenDxp;
use OpenDxp\Bundle\StaticRoutesBundle\Model\Staticroute;
use OpenDxp\Cache\RuntimeCache;
use OpenDxp\Config;
use OpenDxp\Model\Site;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class TestCase extends KernelTestCase
{
    protected static function request(): Request
    {
        $request = Request::create('/');
        $request->setSessionFactory(static fn () => Container::sessionFactory()->createSession());

        return $request;
    }

    protected static function debug(): bool
    {
        return true;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['debug' => static::debug()] + $options);
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        Container::setFactory(static fn () => self::getContainer());
        Browser::reset();
        Container::requestStack()->push(self::request());
    }

    protected function tearDown(): void
    {
        // OpenDXP keeps all of this in statics that outlive a request.
        RuntimeCache::clear();

        Config::setSystemConfiguration(null);

        // A backend request puts the process into admin mode, and only Bootstrap takes it out.
        OpenDxp::unsetAdminMode();

        self::forgetCurrentSite();

        if (class_exists(Staticroute::class)) {
            Staticroute::setCurrentRoute(null);
        }

        parent::tearDown();
    }

    /**
     * Delete this once `Site::setCurrentSite()` accepts null, which it does not today.
     */
    private static function forgetCurrentSite(): void
    {
        (new \ReflectionProperty(Site::class, 'currentSite'))->setValue(null, null);
    }
}
