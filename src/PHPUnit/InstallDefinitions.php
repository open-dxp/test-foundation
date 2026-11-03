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
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

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
        $directories = [
            $parameters->has('directory') ? $parameters->get('directory') : self::DEFAULT_DIRECTORY,
        ];

        if ($parameters->has('opendxp-definitions') && $parameters->get('opendxp-definitions') === 'true') {
            array_unshift($directories, self::openDxpDirectory());
        }

        $found = [];

        foreach (array_keys(self::DEFINITIONS) as $kind) {
            $found[$kind] = self::findDefinitions($directories, $kind);
        }

        if (array_merge(...array_values($found)) === []) {
            return;
        }

        $kernel = self::bootKernel();

        foreach (self::DEFINITIONS as $kind => $installer) {
            foreach ($found[$kind] as $definition) {
                $installer(Path::getFilenameWithoutExtension($definition), $definition);
            }
        }

        $kernel->shutdown();
    }

    /**
     * @param list<string> $directories
     *
     * @return list<string> the definition files in the order of the directories, each directory sorted by name
     */
    private static function findDefinitions(array $directories, string $kind): array
    {
        $filesystem = new Filesystem();
        $definitions = [];

        foreach ($directories as $directory) {
            $kindDirectory = Path::join($directory, $kind);

            if (!$filesystem->exists($kindDirectory)) {
                continue;
            }

            $files = Finder::create()->files()->in($kindDirectory)->depth(0)->name('*.json')->sortByName();

            foreach ($files as $file) {
                $definitions[] = $file->getPathname();
            }
        }

        return $definitions;
    }

    private static function openDxpDirectory(): string
    {
        $file = (new ReflectionClass(ClassDefinitions::class))->getFileName()
            ?: throw new RuntimeException(sprintf('%s has no file.', ClassDefinitions::class));

        return Path::join(Path::getDirectory($file), 'Fixtures');
    }
}
