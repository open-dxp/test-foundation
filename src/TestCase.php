<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

abstract class TestCase extends KernelTestCase
{
    /**
     * A request as the application would see one.
     */
    protected static function request(): Request
    {
        $request = Request::create('/');
        $request->setSessionFactory(static fn () => Container::sessionFactory()->createSession());

        return $request;
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        Container::of(self::getContainer());
        Container::requestStack()->push(self::request());
    }
}
