<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Unit;

use OpenDxp\TestFoundation\Application\ProjectDefinitions;
use Symfony\Component\Filesystem\Filesystem;

function writeDefinition(string $applicationDirectory, string $definition, string $content): string
{
    $path = sprintf('%s/var/classes/%s', $applicationDirectory, $definition);
    (new Filesystem())->dumpFile($path, $content);

    return $path;
}

beforeEach(function () {
    $this->directory = sprintf('%s/project-definitions-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
});

afterEach(function () {
    (new Filesystem())->remove($this->directory);
});

it('puts back a definition an installer wrote over', function (string $definition) {
    $path = writeDefinition($this->directory, $definition, 'project');
    $definitions = ProjectDefinitions::capture($this->directory);
    writeDefinition($this->directory, $definition, 'bundle');

    $definitions->restore();

    expect(file_get_contents($path))->toBe('project');
})->with([
    'a class' => 'definition_Product.php',
    'a field collection' => 'fieldcollections/Adjustment.php',
    'an object brick' => 'objectbricks/Dimensions.php',
]);

it('keeps a definition that only an installer wrote', function () {
    $definitions = ProjectDefinitions::capture($this->directory);
    $path = writeDefinition($this->directory, 'definition_Order.php', 'bundle');

    $definitions->restore();

    expect(file_get_contents($path))->toBe('bundle');
});

it('leaves the generated classes alone', function () {
    $path = writeDefinition($this->directory, 'DataObject/Product.php', 'generated before');
    $definitions = ProjectDefinitions::capture($this->directory);
    writeDefinition($this->directory, 'DataObject/Product.php', 'generated after');

    $definitions->restore();

    expect(file_get_contents($path))->toBe('generated after');
});
