<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Command;

use OpenDxp\TestFoundation\Application\BundleRequirements;
use OpenDxp\TestFoundation\Application\EnvironmentFile;
use OpenDxp\TestFoundation\Application\ProcessRunner;
use OpenDxp\TestFoundation\Application\TestApplication;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'bundle',
    description: 'Builds the application in the working directory around a bundle.',
)]
final class BundleCommand extends Command
{
    private const array ALLOWED_PLUGINS = [
        'open-dxp/*',
        'php-http/discovery',
        'symfony/flex',
        'symfony/runtime',
        'pestphp/pest-plugin',
    ];

    protected function configure(): void
    {
        $this
            ->addArgument('bundle', InputArgument::REQUIRED, 'Path of the bundle checkout')
            ->addOption('opendxp', null, InputOption::VALUE_REQUIRED, 'Constraint for open-dxp/opendxp to force');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $applicationDirectory = TestApplication::workingDirectory();
        $bundleDirectory = realpath($input->getArgument('bundle'))
            ?: throw new RuntimeException(sprintf('There is no bundle at %s.', $input->getArgument('bundle')));
        $bundleManifest = self::readManifest($bundleDirectory);

        $this->writeApplicationManifest($applicationDirectory, $bundleDirectory, $bundleManifest);

        $composer = [
            PHP_BINARY,
            (new ExecutableFinder())->find('composer') ?? throw new RuntimeException('composer is not on the PATH.'),
        ];
        $runner = new ProcessRunner($output, $applicationDirectory);

        // One resolution over everything, so that the bundle's requirements can still move what the foundation
        // brought, for example back to Pest 4 when a development dependency does not allow Pest 5.
        $requirements = BundleRequirements::collect($bundleManifest, $input->getOption('opendxp'));
        $runner->mustRun([...$composer, 'require', '--no-update', '--no-interaction', ...$requirements]);
        $runner->mustRun([...$composer, 'update', '--no-interaction', '--no-progress', '--no-scripts']);

        $this->copyApplicationTemplate($applicationDirectory);
        $this->copyTestSuite($applicationDirectory, $bundleDirectory);
        EnvironmentFile::writeFromTemplate($applicationDirectory);

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $bundleManifest
     */
    private function writeApplicationManifest(
        string $applicationDirectory,
        string $bundleDirectory,
        array $bundleManifest,
    ): void {
        $manifest = self::readManifest($applicationDirectory);

        $repositories = array_values($manifest['repositories'] ?? []);

        // Composer asks the repositories in order, so the checkout wins over a registry with a release of the bundle.
        // A caller who already offers the checkout may have given it a version, and that repository stays.
        if (!$this->offersDirectory($repositories, $bundleDirectory)) {
            array_unshift($repositories, $this->checkoutRepository($bundleDirectory, $bundleManifest['name']));
        }

        $manifest['repositories'] = $repositories;

        $manifest['minimum-stability'] = 'dev';
        $manifest['prefer-stable'] = true;
        $manifest['config']['sort-packages'] = true;
        $manifest['config']['allow-plugins'] = array_fill_keys(self::ALLOWED_PLUGINS, true);
        $manifest['extra']['opendxp-test']['package'] = $bundleManifest['name'];
        $manifest['autoload']['psr-4']['OpenDxp\\Model\\DataObject\\'] = 'var/classes/DataObject';
        $manifest['autoload-dev']['psr-4'] = array_map(
            fn (string|array $paths): string|array => $this->locateDevelopmentPaths($paths, $bundleManifest['name']),
            $bundleManifest['autoload-dev']['psr-4'] ?? [],
        );

        (new Filesystem())->dumpFile(
            Path::join($applicationDirectory, 'composer.json'),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
    }

    /**
     * @param string|array<string> $paths
     *
     * @return string|array<string>
     */
    private function locateDevelopmentPaths(string|array $paths, string $package): string|array
    {
        if (is_array($paths)) {
            return array_map(fn (string $path): string => $this->locateDevelopmentPath($path, $package), $paths);
        }

        return $this->locateDevelopmentPath($paths, $package);
    }

    /**
     * Returns the path in the application. The tests are copied into the application. Every other development path
     * stays in the installed package.
     */
    private function locateDevelopmentPath(string $path, string $package): string
    {
        if (str_starts_with(trim($path, './'), 'tests')) {
            return $path;
        }

        return sprintf('vendor/%s/%s', $package, ltrim($path, './'));
    }

    /**
     * Returns a path repository for the checkout.
     *
     * A checkout of a branch has no version that a constraint such as ^1.3 accepts. The repository reports the major
     * version of its newest tag followed by .99.99 instead.
     *
     * @return array<string, mixed>
     */
    private function checkoutRepository(string $directory, string $package): array
    {
        $repository = ['type' => 'path', 'url' => $directory];
        $newestTag = new Process(['git', '-C', $directory, 'describe', '--tags', '--abbrev=0']);
        $newestTag->run();

        if (preg_match('/(\d+)\./', $newestTag->getOutput(), $match)) {
            $repository['options'] = ['versions' => [$package => $match[1] . '.99.99']];
        }

        return $repository;
    }

    /**
     * @param list<array<string, mixed>> $repositories
     */
    private function offersDirectory(array $repositories, string $directory): bool
    {
        foreach ($repositories as $repository) {
            if (($repository['type'] ?? null) === 'path' && realpath($repository['url']) === $directory) {
                return true;
            }
        }

        return false;
    }

    private function copyApplicationTemplate(string $applicationDirectory): void
    {
        $foundationDirectory = Path::join($applicationDirectory, 'vendor', BundleRequirements::FOUNDATION);
        $template = self::readManifest($foundationDirectory)['extra']['app-template'];

        $filesystem = new Filesystem();
        $filesystem->mirror(
            Path::join($foundationDirectory, $template),
            $applicationDirectory,
            options: ['override' => true],
        );
        $filesystem->mkdir(Path::join($applicationDirectory, 'public'));
    }

    /**
     * Copies the tests into the application. Pest names a test after its path below the application, so the tests
     * cannot be reached through vendor.
     */
    private function copyTestSuite(string $applicationDirectory, string $bundleDirectory): void
    {
        $filesystem = new Filesystem();
        $filesystem->copy(
            Path::join($bundleDirectory, 'phpunit.xml.dist'),
            Path::join($applicationDirectory, 'phpunit.xml'),
            overwriteNewerFiles: true,
        );
        $filesystem->remove(Path::join($applicationDirectory, 'tests'));
        $filesystem->mirror(Path::join($bundleDirectory, 'tests'), Path::join($applicationDirectory, 'tests'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function readManifest(string $directory): array
    {
        $manifest = (new Filesystem())->readFile(Path::join($directory, 'composer.json'));

        return json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
    }
}
