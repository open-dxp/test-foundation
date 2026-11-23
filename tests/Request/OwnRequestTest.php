<?php

declare(strict_types=1);

use OpenDxp\TestFoundation\Container;

it('runs in the request its test case builds', function () {
    expect(Container::requestStack()->getCurrentRequest()?->getRequestUri())->toBe('/de');
});
