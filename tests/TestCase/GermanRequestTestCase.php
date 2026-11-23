<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\TestCase;

use OpenDxp\TestFoundation\Container;
use OpenDxp\TestFoundation\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Runs each test in a request for /de.
 */
abstract class GermanRequestTestCase extends TestCase
{
    protected static function request(): Request
    {
        $request = Container::createRequest();
        $request->server->set('REQUEST_URI', '/de');

        return $request;
    }
}
