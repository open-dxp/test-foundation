<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Command;

use OpenDxp\TestFoundation\Application\ProcessRunner;
use OpenDxp\TestFoundation\Application\TestApplication;
use LogicException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'analyse',
    description: 'Runs the static checks of a package against the application in the working directory.',
)]
final class AnalyseCommand extends Command
{
    private const string LINT = 'lint';

    /**
     * A project lints its templates in the first of these environments it configures under config/packages/.
     */
    private const array DEPLOYED_ENVIRONMENTS = ['prod', 'production'];

    private const array TOOL_CONFIGURATION_FILES = [
        'phpstan'     => 'phpstan.neon',
        'deptrac'     => 'deptrac.yaml',
        'phparkitect' => 'phparkitect.php',
    ];

    protected function configure(): void
    {
        $this
            ->addArgument('package', InputArgument::REQUIRED, 'Path of the bundle, or of the project repository')
            ->addArgument('check', InputArgument::OPTIONAL, sprintf(
                'One of %s. All configured checks by default.',
                implode(', ', [self::LINT, ...array_keys(self::TOOL_CONFIGURATION_FILES)]),
            ))
            ->addOption(
                'baseline',
                null,
                InputOption::VALUE_NONE,
                'Write the PHPStan baseline beside phpstan.neon instead of reporting',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = TestApplication::fromWorkingDirectory();
        $runner = new ProcessRunner($output, $application->directory);
        $packageDirectory = realpath($input->getArgument('package'))
            ?: throw new RuntimeException(sprintf('There is no package at %s.', $input->getArgument('package')));

        $toolConfigurations = $this->findToolConfigurations($application, $packageDirectory);
        $writeBaseline = (bool) $input->getOption('baseline');
        $requestedCheck = $writeBaseline ? 'phpstan' : $input->getArgument('check');
        $exitCodes = [self::SUCCESS];

        foreach ($this->selectChecks($toolConfigurations, $packageDirectory, $requestedCheck) as $check) {
            $output->writeln(sprintf('<info>%s</info>', $check));

            $exitCodes[] = $check === self::LINT
                ? $this->runLint($runner, $application, $packageDirectory)
                : $this->runTool($runner, $application, $check, $toolConfigurations[$check], $writeBaseline);
        }

        return max($exitCodes) === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, string> tool => configuration file
     */
    private function findToolConfigurations(TestApplication $application, string $packageDirectory): array
    {
        $toolConfigurations = [];

        foreach (self::TOOL_CONFIGURATION_FILES as $tool => $fileName) {
            $configurationFile = $this->findConfigurationFile($application, $packageDirectory, $fileName);

            if ($configurationFile !== null) {
                $toolConfigurations[$tool] = $configurationFile;
            }
        }

        return $toolConfigurations;
    }

    /**
     * @param array<string, string> $toolConfigurations
     *
     * @return list<string>
     */
    private function selectChecks(array $toolConfigurations, string $packageDirectory, ?string $requestedCheck): array
    {
        if ($requestedCheck === null) {
            return [self::LINT, ...array_keys($toolConfigurations)];
        }

        if ($requestedCheck === self::LINT) {
            return [self::LINT];
        }

        if (!isset(self::TOOL_CONFIGURATION_FILES[$requestedCheck])) {
            throw new RuntimeException(sprintf('There is no check called %s.', $requestedCheck));
        }

        if (!isset($toolConfigurations[$requestedCheck])) {
            throw new RuntimeException(sprintf(
                '%s has no %s.',
                $packageDirectory,
                self::TOOL_CONFIGURATION_FILES[$requestedCheck],
            ));
        }

        return [$requestedCheck];
    }

    /**
     * Finds the file beside the application or in a directory above it, up to the root of the package.
     */
    private function findConfigurationFile(
        TestApplication $application,
        string $packageDirectory,
        string $fileName,
    ): ?string {
        $filesystem = new Filesystem();
        $directory = Path::isBasePath($packageDirectory, $application->directory)
            ? $application->directory
            : $packageDirectory;

        while (true) {
            $configurationFile = Path::join($directory, $fileName);

            if ($filesystem->exists($configurationFile)) {
                return $configurationFile;
            }

            if ($directory === $packageDirectory) {
                return null;
            }

            $directory = Path::getDirectory($directory);
        }
    }

    private function runLint(ProcessRunner $runner, TestApplication $application, string $packageDirectory): int
    {
        $filesystem = new Filesystem();
        $environment = $application->consoleEnvironment();
        $exitCodes = [
            $runner->run($application->consoleCommand('lint:container'), $environment),
            $runner->run($application->consoleCommand('lint:yaml', 'config'), $environment),
        ];

        // A bundle cannot know where it will run, so only a project lints its templates where it is deployed.
        $twigOptions = $this->isBundle($application, $packageDirectory)
            ? []
            : ['--env=' . $this->findDeployedEnvironment($application), '--no-debug'];

        foreach (['themes', 'templates', Path::join($packageDirectory, 'templates')] as $templateDirectory) {
            if ($filesystem->exists(Path::makeAbsolute($templateDirectory, $application->directory))) {
                $lintTwig = $application->consoleCommand('lint:twig', $templateDirectory, ...$twigOptions);
                $exitCodes[] = $runner->run($lintTwig, $environment);
            }
        }

        return max($exitCodes);
    }

    private function findDeployedEnvironment(TestApplication $application): string
    {
        $filesystem = new Filesystem();

        foreach (self::DEPLOYED_ENVIRONMENTS as $environment) {
            if ($filesystem->exists(Path::join($application->directory, 'config/packages', $environment))) {
                return $environment;
            }
        }

        return self::DEPLOYED_ENVIRONMENTS[0];
    }

    private function isBundle(TestApplication $application, string $packageDirectory): bool
    {
        $filesystem = new Filesystem();
        $manifestFile = Path::join($packageDirectory, 'composer.json');

        if (!$filesystem->exists($manifestFile)) {
            return false;
        }

        $manifest = json_decode($filesystem->readFile($manifestFile), true, flags: JSON_THROW_ON_ERROR);
        $packageName = $manifest['name'] ?? '';

        if ($packageName === '') {
            return false;
        }

        return $filesystem->exists(Path::join($application->directory, 'vendor', $packageName));
    }

    private function runTool(
        ProcessRunner $runner,
        TestApplication $application,
        string $tool,
        string $configurationFile,
        bool $writeBaseline,
    ): int {
        if (!(new Filesystem())->exists(Path::join($application->directory, 'vendor/bin', $tool))) {
            throw new RuntimeException(sprintf(
                '%s is configured but not installed. The package requires it in require-dev.',
                $tool,
            ));
        }

        $environment = $application->consoleEnvironment();

        if ($tool === 'phpstan') {
            // PHPStan reads the debug container. The warmup compiles it only when the configuration changed.
            $runner->mustRun($application->consoleCommand('cache:warmup', '-q'), [...$environment, 'APP_DEBUG' => '1']);
        }

        $baseline = Path::join(Path::getDirectory($configurationFile), 'phpstan-baseline.neon');
        $baselineArguments = $writeBaseline ? ['--generate-baseline=' . $baseline] : [];

        $arguments = match ($tool) {
            'phpstan' => ['analyse', '-c', $configurationFile, ...$baselineArguments],
            'deptrac' => ['analyse', '-c', $configurationFile],
            'phparkitect' => ['check', '--config=' . $configurationFile],
            default => throw new LogicException(sprintf('There is no tool called %s.', $tool)),
        };

        return $runner->run($application->vendorBinaryCommand($tool, ...$arguments), $environment);
    }
}
