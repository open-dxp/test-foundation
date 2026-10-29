<?php

declare(strict_types=1);

use OpenDxp\Config;

beforeAll(fn () => Config::setSystemConfiguration(['general' => ['domain' => 'default.test']]));

it('reads the system configuration of its own environment', function () {
    $domain = Config::getSystemConfiguration('general')['domain'];

    expect($domain)->toBe('environment.test');
});
