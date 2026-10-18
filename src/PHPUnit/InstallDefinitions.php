<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\PHPUnit;

use OpenDxp\Test\ClassDefinitions;
use OpenDxp\Test\ClassificationStores;
use OpenDxp\Test\Fieldcollections;
use OpenDxp\Test\ObjectBricks;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

final class InstallDefinitions implements Extension
{
    use BootKernelTrait;

    private const string DEFAULT_DIRECTORY = 'tests/Fixtures';

    // Classes refer to classification stores and field collections, and object bricks refer to classes.
    // Each kind is installed after the kinds it refers to.
    private const array DEFINITIONS = [
        'classificationstores' => [ClassificationStores::class, 'install'],
        'fieldcollections'     => [Fieldcollections::class, 'install'],
        'classes'              => [ClassDefinitions::class, 'install'],
        'objectbricks'         => [ObjectBricks::class, 'install'],
    ];

    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        $directory = $parameters->has('directory')
            ? $parameters->get('directory')
            : self::DEFAULT_DIRECTORY;

        $found = [];

        foreach (self::DEFINITIONS as $kind => $installer) {
            $found[$kind] = glob(sprintf('%s/%s/*.json', $directory, $kind)) ?: [];
        }

        if ($found === array_fill_keys(array_keys(self::DEFINITIONS), [])) {
            return;
        }

        $kernel = self::bootKernel();

        foreach (self::DEFINITIONS as $kind => $installer) {
            foreach ($found[$kind] as $definition) {
                $installer(basename($definition, '.json'), $definition);
            }
        }

        $kernel->shutdown();
    }
}
