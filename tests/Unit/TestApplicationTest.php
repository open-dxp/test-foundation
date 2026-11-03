<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Unit;

use OpenDxp\TestFoundation\Application\TestApplication;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function () {
    $this->workingDirectory = getcwd();
    $this->directory = sprintf('%s/test-application-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));

    $filesystem = new Filesystem();
    $filesystem->mkdir([
        $this->directory . '/src/Migrations',
        $this->directory . '/vendor/acme/blog/src/Migrations',
    ]);

    $filesystem->dumpFile(
        $this->directory . '/phpunit.xml.dist',
        '<phpunit><php><env name="KERNEL_CLASS" value="App\Tests\TestKernel"/></php></phpunit>',
    );

    chdir($this->directory);
});

afterEach(function () {
    chdir($this->workingDirectory);
    (new Filesystem())->remove($this->directory);
});

it('returns the migration namespaces of the application', function () {
    $namespaces = TestApplication::fromWorkingDirectory()->migrationNamespacesOfApplication([
        'App\Migrations' => $this->directory . '/src/Migrations',
    ]);

    expect($namespaces)->toBe(['App\Migrations']);
});

it('leaves out the migration namespaces of packages', function (string $path) {
    $namespaces = TestApplication::fromWorkingDirectory()->migrationNamespacesOfApplication([
        'Acme\Blog\Migrations' => str_replace('%application%', $this->directory, $path),
    ]);

    expect($namespaces)->toBe([]);
})->with([
    'a bundle' => '@AcmeBlogBundle/src/Migrations',
    'a directory in vendor' => '%application%/vendor/acme/blog/src/Migrations',
    'a directory outside the application' => '/tmp',
    'a missing directory' => '%application%/src/Missing',
]);
