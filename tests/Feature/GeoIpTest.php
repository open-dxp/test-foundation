<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Feature;

use InvalidArgumentException;
use OpenDxp\TestFoundation\Container;
use OpenDxp\TestFoundation\GeoIp;

it('points the application at the test database', function () {
    expect(Container::parameter('opendxp.geoip.db_file'))
        ->toBe(realpath(GeoIp::DATABASE))
        ->toBeFile();
});

it('names an address in a country the test database knows', function () {
    expect(GeoIp::addressIn('CH'))->toBe('5.148.191.255');
});

it('refuses a country the test database does not know', function () {
    GeoIp::addressIn('JP');
})->throws(InvalidArgumentException::class, 'knows no address in JP');
