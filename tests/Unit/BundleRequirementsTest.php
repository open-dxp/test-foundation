<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Unit;

use OpenDxp\TestFoundation\Application\BundleRequirements;
use RuntimeException;

/**
 * @param array<string, mixed> $extra
 *
 * @return array<string, mixed>
 */
function bundleManifest(array $extra): array
{
    return [
        'name' => 'open-dxp/demo-bundle',
        'require-dev' => [
            'open-dxp/test-foundation' => '^1.0',
            'symfony/maker-bundle' => '^1.67',
        ],
        'extra' => $extra,
    ];
}

it('requires the bundle and its development requirements with their constraints', function () {
    $requirements = BundleRequirements::collect(bundleManifest([]), null);

    expect($requirements)->toBe([
        'open-dxp/demo-bundle:*@dev',
        'symfony/maker-bundle:^1.67',
    ]);
});

it('requires an optional package with the constraint the bundle names', function () {
    $manifest = bundleManifest([
        'opendxp-test' => [
            'optional' => ['open-dxp/formbuilder-bundle' => '^1.0'],
        ],
    ]);

    $requirements = BundleRequirements::collect($manifest, null);

    expect($requirements)->toContain('open-dxp/formbuilder-bundle:^1.0');
});

it('requires an optional package without a constraint in any version', function () {
    $manifest = bundleManifest([
        'opendxp-test' => [
            'optional' => ['open-dxp/formbuilder-bundle'],
        ],
    ]);

    $requirements = BundleRequirements::collect($manifest, null);

    expect($requirements)->toContain('open-dxp/formbuilder-bundle:*');
});

it('forces the constraint given for OpenDXP', function () {
    $requirements = BundleRequirements::collect(bundleManifest([]), '1.x-dev as 1.99.9');

    expect($requirements)->toContain('open-dxp/opendxp:1.x-dev as 1.99.9');
});

it('refuses a bundle that does not require the foundation', function () {
    BundleRequirements::collect(['name' => 'open-dxp/demo-bundle'], null);
})->throws(RuntimeException::class, 'open-dxp/demo-bundle does not require open-dxp/test-foundation in require-dev.');
