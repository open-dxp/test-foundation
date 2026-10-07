<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Application;

use RuntimeException;

final class BundleRequirements
{
    public const string FOUNDATION = 'open-dxp/test-foundation';

    /**
     * @param array<string, mixed> $bundleManifest
     *
     * @return list<string>
     */
    public static function collect(array $bundleManifest, ?string $opendxpConstraint): array
    {
        // Composer installs require-dev only for the root package, and the bundle is a dependency here.
        $devRequirements = $bundleManifest['require-dev'] ?? [];

        // The foundation testing itself is installed already, as the package under test.
        if ($bundleManifest['name'] !== self::FOUNDATION && !isset($devRequirements[self::FOUNDATION])) {
            throw new RuntimeException(sprintf('%s does not require %s in require-dev.', $bundleManifest['name'], self::FOUNDATION));
        }

        unset($devRequirements[self::FOUNDATION]);

        $requirements = [$bundleManifest['name'] . ':*@dev'];

        foreach ($devRequirements as $package => $constraint) {
            $requirements[] = $package . ':' . $constraint;
        }

        foreach (self::optionalRequirements($bundleManifest) as $package => $constraint) {
            $requirements[] = $package . ':' . $constraint;
        }

        // Core as the bundle under test is the checkout itself and cannot be forced to another version.
        if ($opendxpConstraint !== null && $bundleManifest['name'] !== 'open-dxp/opendxp') {
            $requirements[] = 'open-dxp/opendxp:' . $opendxpConstraint;
        }

        return $requirements;
    }

    /**
     * @param array<string, mixed> $bundleManifest
     *
     * @return array<string, string>
     */
    private static function optionalRequirements(array $bundleManifest): array
    {
        $optional = $bundleManifest['extra']['opendxp-test']['optional'] ?? [];

        // A list names the packages without a constraint, so each of them takes any version.
        return array_is_list($optional) ? array_fill_keys($optional, '*') : $optional;
    }
}
