<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Command;

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
use Symfony\Component\Process\ExecutableFinder;

#[AsCommand(
    name: 'bundle',
    description: 'Completes the application in the working directory around a bundle. Run it after requiring this package.',
)]
final class BundleCommand extends Command
{
    private const string FOUNDATION = 'open-dxp/test-foundation';

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
        $bundleManifest = self::readJson($bundleDirectory . '/composer.json');

        $this->writeApplicationManifest($applicationDirectory, $bundleDirectory, $bundleManifest);

        (new ProcessRunner($output, $applicationDirectory))->mustRun([
            PHP_BINARY,
            (new ExecutableFinder())->find('composer') ?? throw new RuntimeException('composer is not on the PATH.'),
            'require',
            '--no-interaction',
            '--no-progress',
            '--no-scripts',
            '--with-all-dependencies',
            ...$this->collectRequirements($bundleManifest, $input->getOption('opendxp')),
        ]);

        $this->copyApplicationTemplate($applicationDirectory);
        $this->copyTestSuite($applicationDirectory, $bundleDirectory);
        EnvironmentFile::writeFromTemplate($applicationDirectory);

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $bundleManifest
     *
     * @return list<string>
     */
    private function collectRequirements(array $bundleManifest, ?string $opendxpConstraint): array
    {
        // Composer installs require-dev only for the root package, and the bundle is a dependency here.
        $devRequirements = $bundleManifest['require-dev'] ?? [];

        if (!isset($devRequirements[self::FOUNDATION])) {
            throw new RuntimeException(sprintf('%s does not require %s in require-dev.', $bundleManifest['name'], self::FOUNDATION));
        }

        unset($devRequirements[self::FOUNDATION]);

        $requirements = [$bundleManifest['name'] . ':*@dev'];

        foreach ($devRequirements as $package => $constraint) {
            $requirements[] = $package . ':' . $constraint;
        }

        foreach ($bundleManifest['extra']['opendxp-test']['optional'] ?? [] as $package) {
            $requirements[] = $package . ':*';
        }

        if ($opendxpConstraint !== null) {
            $requirements[] = 'open-dxp/opendxp:' . $opendxpConstraint;
        }

        return $requirements;
    }

    /**
     * @param array<string, mixed> $bundleManifest
     */
    private function writeApplicationManifest(string $applicationDirectory, string $bundleDirectory, array $bundleManifest): void
    {
        $manifest = self::readJson($applicationDirectory . '/composer.json');

        $repositories = array_values($manifest['repositories'] ?? []);

        // Composer asks the repositories in order, so the checkout wins over a registry with a release of the bundle.
        // A caller who already offers the checkout may have given it a version, and that repository stays.
        if (!$this->offersDirectory($repositories, $bundleDirectory)) {
            array_unshift($repositories, ['type' => 'path', 'url' => $bundleDirectory]);
        }

        $manifest['repositories'] = $repositories;

        $manifest['minimum-stability'] = 'dev';
        $manifest['prefer-stable'] = true;
        $manifest['config']['sort-packages'] = true;
        $manifest['config']['allow-plugins'] = array_fill_keys(self::ALLOWED_PLUGINS, true);
        $manifest['autoload']['psr-4']['OpenDxp\\Model\\DataObject\\'] = 'var/classes/DataObject';
        $manifest['autoload-dev']['psr-4'] = $bundleManifest['autoload-dev']['psr-4'] ?? [];

        file_put_contents(
            $applicationDirectory . '/composer.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
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
        $foundationDirectory = $applicationDirectory . '/vendor/' . self::FOUNDATION;
        $templateDirectory = $foundationDirectory . '/' . self::readJson($foundationDirectory . '/composer.json')['extra']['app-template'];

        $filesystem = new Filesystem();
        $filesystem->mirror($templateDirectory, $applicationDirectory, null, ['override' => true]);
        $filesystem->mkdir($applicationDirectory . '/public');
    }

    /**
     * Pest names a test after its path below the application, so the tests are copied and not reached through vendor.
     */
    private function copyTestSuite(string $applicationDirectory, string $bundleDirectory): void
    {
        $filesystem = new Filesystem();
        $filesystem->copy($bundleDirectory . '/phpunit.xml.dist', $applicationDirectory . '/phpunit.xml', true);
        $filesystem->remove($applicationDirectory . '/tests');
        $filesystem->mirror($bundleDirectory . '/tests', $applicationDirectory . '/tests');
    }

    /**
     * @return array<string, mixed>
     */
    private static function readJson(string $file): array
    {
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('%s does not exist.', $file));
        }

        return json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }
}
