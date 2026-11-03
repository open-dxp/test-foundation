<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;

final class GeoIp
{
    public const string DATABASE = __DIR__ . '/../fixtures/geoip/geoip.mmdb';

    private const string COUNTRIES = __DIR__ . '/../fixtures/geoip/countries.json';

    public static function addressIn(string $country): string
    {
        $countries = json_decode((new Filesystem())->readFile(self::COUNTRIES), true, flags: JSON_THROW_ON_ERROR);

        return $countries[$country]['address'] ?? throw new InvalidArgumentException(sprintf(
            'The test database knows no address in %s. It knows %s.',
            $country,
            implode(', ', array_keys($countries)),
        ));
    }
}
