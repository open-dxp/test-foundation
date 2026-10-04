<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Command;

use OpenDxp\TestFoundation\Application\EnvironmentFile;
use OpenDxp\TestFoundation\Application\ProcessRunner;
use OpenDxp\TestFoundation\Application\TestApplication;
use OpenDxp\TestFoundation\Dto\DatabaseConnection;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'install',
    description: 'Installs OpenDXP, its bundles and the data object classes into the application in the working directory.',
)]
final class InstallCommand extends Command
{
    private const string ADMIN_USERNAME = 'admin';
    private const string ADMIN_PASSWORD = 'testtesttest';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Symfony's runtime does not start without a .env, and a project keeps its own out of the repository.
        if (!is_file(TestApplication::workingDirectory() . '/.env')) {
            EnvironmentFile::writeFromTemplate(TestApplication::workingDirectory());
        }

        $application = TestApplication::fromWorkingDirectory();
        $runner = new ProcessRunner($output, $application->directory);
        $database = DatabaseConnection::fromUrl($_SERVER['DATABASE_URL'] ?? '');

        // Without debug, a command does not compile the container again after a bundle install changed the configuration.
        $environment = [...$application->consoleEnvironment(), 'APP_DEBUG' => '0'];

        $output->writeln('Installing OpenDXP');
        $runner->mustRun($application->vendorBinaryCommand(
            'opendxp-install',
            '--mysql-host-socket=' . $database->host,
            '--mysql-port=' . $database->port,
            '--mysql-username=' . $database->user,
            '--mysql-password=' . $database->password,
            '--mysql-database=' . $database->database,
            '--admin-username=' . self::ADMIN_USERNAME,
            '--admin-password=' . self::ADMIN_PASSWORD,
            '--skip-database-config',
            '--no-interaction',
        ), $environment);

        // An uninstalled bundle creates its tables inside the first test that needs them, and that DDL ends the test's transaction.
        // A bundle can become installable only once another one is installed, so the list is asked again after each round.
        $installed = [];

        while ($bundles = array_diff($this->findUninstalledBundles($application, $environment), $installed)) {
            foreach ($bundles as $bundle) {
                $output->writeln('Installing ' . $bundle);
                $runner->mustRun($application->consoleCommand('opendxp:bundle:install', $bundle, '--no-post-change-commands', '-q'), $environment);
                $installed[] = $bundle;
            }
        }

        // An installer that skips parent::install() leaves its bundle installable, and one that does not mark its
        // migrations leaves them to run on top of the state it already built.
        $notMarked = $this->findUninstalledBundles($application, $environment);

        if ($notMarked !== []) {
            throw new RuntimeException(sprintf('%s ran its installer but is not marked as installed.', implode(', ', $notMarked)));
        }

        foreach ($application->migrationNamespacesOfPackageUnderTest() as $namespace) {
            try {
                $runner->mustRun($application->consoleCommand('doctrine:migrations:up-to-date', '--prefix=' . $namespace . '\\'), $environment);
            } catch (RuntimeException) {
                throw new RuntimeException(sprintf(
                    'The installation leaves migrations of %s to run. The installer calls markMigrationsAsExecuted() in install().',
                    $namespace,
                ));
            }
        }

        $runner->mustRun($application->consoleCommand('assets:install', 'public', '-q'), $environment);

        if (glob($application->directory . '/var/classes/definition_*.php')) {
            $output->writeln('Creating the data object classes');
            $runner->mustRun($application->consoleCommand('opendxp:deployment:classes-rebuild', '--create-classes', '-n', '-q'), $environment);
        }

        // PHPStan reads the container file that only a debug boot writes.
        $output->writeln('Warming the cache');
        $runner->mustRun($application->consoleCommand('cache:warmup', '-q'), [...$environment, 'APP_DEBUG' => '1']);

        return self::SUCCESS;
    }

    /**
     * @param array<string, string> $environment
     *
     * @return list<string>
     */
    private function findUninstalledBundles(TestApplication $application, array $environment): array
    {
        $bundleList = new Process($application->consoleCommand('opendxp:bundle:list', '--json'), $application->directory, $environment);
        $bundleList->mustRun();

        $uninstalled = [];

        foreach (json_decode($bundleList->getOutput(), true, flags: JSON_THROW_ON_ERROR) as $bundle) {
            if ($bundle['Enabled'] && $bundle['Installable'] && !$bundle['Installed']) {
                $uninstalled[] = $bundle['Bundle'];
            }
        }

        return $uninstalled;
    }
}
