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
use Symfony\Component\Process\Process;

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

        $composer = [
            PHP_BINARY,
            (new ExecutableFinder())->find('composer') ?? throw new RuntimeException('composer is not on the PATH.'),
        ];
        $runner = new ProcessRunner($output, $applicationDirectory);

        // One resolution over everything, so that the bundle's requirements can still move what the foundation
        // brought, for example back to Pest 4 when a development dependency does not allow Pest 5.
        $runner->mustRun([...$composer, 'require', '--no-update', '--no-interaction', ...$this->collectRequirements($bundleManifest, $input->getOption('opendxp'))]);
        $runner->mustRun([...$composer, 'update', '--no-interaction', '--no-progress', '--no-scripts']);

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

        // The foundation testing itself is installed already, as the package under test.
        if ($bundleManifest['name'] !== self::FOUNDATION && !isset($devRequirements[self::FOUNDATION])) {
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

        // Core as the bundle under test is the checkout itself and cannot be forced to another version.
        if ($opendxpConstraint !== null && $bundleManifest['name'] !== 'open-dxp/opendxp') {
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
            array_unshift($repositories, $this->checkoutRepository($bundleDirectory, $bundleManifest['name']));
        }

        $manifest['repositories'] = $repositories;

        $manifest['minimum-stability'] = 'dev';
        $manifest['prefer-stable'] = true;
        $manifest['config']['sort-packages'] = true;
        $manifest['config']['allow-plugins'] = array_fill_keys(self::ALLOWED_PLUGINS, true);
        $manifest['extra']['opendxp-test']['package'] = $bundleManifest['name'];
        $manifest['autoload']['psr-4']['OpenDxp\\Model\\DataObject\\'] = 'var/classes/DataObject';
        $manifest['autoload-dev']['psr-4'] = $bundleManifest['autoload-dev']['psr-4'] ?? [];

        file_put_contents(
            $applicationDirectory . '/composer.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
    }

    /**
     * A checkout of a branch has no version that a constraint such as ^1.3 accepts. It reports the
     * major version of its newest tag followed by .99.99 instead.
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
