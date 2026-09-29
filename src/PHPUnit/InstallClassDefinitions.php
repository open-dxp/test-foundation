<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\PHPUnit;

use OpenDxp\TestFoundation\ClassDefinitions;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Installs the data object classes a package keeps beside its tests, once, before the run.
 *
 * Installing a class creates tables for it, and MySQL commits on any DDL, which would end the
 * transaction the tests are isolated by. So it cannot happen inside a test, and not in a
 * `beforeAll` either: that runs while the previous class's transaction is still open. Before the
 * runner has started anything is the only moment where no transaction is in the way.
 *
 * A package that defines data object classes asks for this beside the one it already has:
 *
 *     <extensions>
 *         <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
 *         <bootstrap class="OpenDxp\TestFoundation\PHPUnit\InstallClassDefinitions">
 *             <parameter name="directory" value="tests/Fixtures/classes"/>
 *         </bootstrap>
 *     </extensions>
 *
 * Every `*.json` in that directory becomes a class named after the file.
 */
final class InstallClassDefinitions implements Extension
{
    private const string DEFAULT_DIRECTORY = 'tests/Fixtures/classes';

    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        $directory = $parameters->has('directory')
            ? $parameters->get('directory')
            : self::DEFAULT_DIRECTORY;

        $definitions = glob($directory . '/*.json') ?: [];

        if ($definitions === []) {
            return;
        }

        $kernel = self::boot();

        foreach ($definitions as $definition) {
            ClassDefinitions::install(basename($definition, '.json'), $definition);
        }

        $kernel->shutdown();
    }

    /**
     * The application, built the way the test cases build it. Nothing has booted one yet at this
     * point in the run, and installing a class needs the database behind it.
     */
    private static function boot(): KernelInterface
    {
        $class = $_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS'] ?? getenv('KERNEL_CLASS');

        if (!is_string($class) || !class_exists($class)) {
            throw new RuntimeException(
                'KERNEL_CLASS names no kernel, so the class definitions cannot be installed. '
                . 'It is set in the <php> section of phpunit.xml.',
            );
        }

        $kernel = new $class('test', true);
        $kernel->boot();

        return $kernel;
    }
}
