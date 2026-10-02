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

#[AsCommand(
    name: 'analyse',
    description: 'Runs the static checks a package configures, against the installed application in the working directory.',
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
            ->addOption('baseline', null, InputOption::VALUE_NONE, 'Write the PHPStan baseline beside phpstan.neon instead of reporting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = TestApplication::fromWorkingDirectory();
        $runner = new ProcessRunner($output, $application->directory);
        $packageDirectory = realpath($input->getArgument('package'))
            ?: throw new RuntimeException(sprintf('There is no package at %s.', $input->getArgument('package')));

        $toolConfigurations = $this->findToolConfigurations($application, $packageDirectory);
        $failed = false;

        $writeBaseline = (bool) $input->getOption('baseline');
        $requestedCheck = $writeBaseline ? 'phpstan' : $input->getArgument('check');

        foreach ($this->selectChecks($toolConfigurations, $packageDirectory, $requestedCheck) as $check) {
            $output->writeln(sprintf('<info>%s</info>', $check));

            $exitCode = $check === self::LINT
                ? $this->runLint($runner, $application, $packageDirectory)
                : $this->runTool($runner, $application, $check, $toolConfigurations[$check], $writeBaseline);

            $failed = $failed || $exitCode !== 0;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
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
            throw new RuntimeException(sprintf('%s has no %s.', $packageDirectory, self::TOOL_CONFIGURATION_FILES[$requestedCheck]));
        }

        return [$requestedCheck];
    }

    /**
     * A project may keep the file beside its application or in any directory above, up to the repository root.
     */
    private function findConfigurationFile(TestApplication $application, string $packageDirectory, string $fileName): ?string
    {
        $isInsidePackage = str_starts_with($application->directory . '/', $packageDirectory . '/');
        $directory = $isInsidePackage ? $application->directory : $packageDirectory;

        while (true) {
            if (is_file($directory . '/' . $fileName)) {
                return $directory . '/' . $fileName;
            }

            if ($directory === $packageDirectory) {
                return null;
            }

            $directory = dirname($directory);
        }
    }

    private function runLint(ProcessRunner $runner, TestApplication $application, string $packageDirectory): int
    {
        $environment = $application->consoleEnvironment();
        $exitCode = $runner->run($application->consoleCommand('lint:container'), $environment);
        $exitCode = $runner->run($application->consoleCommand('lint:yaml', 'config'), $environment) ?: $exitCode;

        // A bundle cannot know where it will run, so only a project lints its templates where it is deployed.
        $twigOptions = $this->isBundle($application, $packageDirectory)
            ? []
            : ['--env=' . $this->findDeployedEnvironment($application), '--no-debug'];

        foreach (['themes', 'templates', $packageDirectory . '/templates'] as $templateDirectory) {
            if (is_dir($templateDirectory) || is_dir($application->directory . '/' . $templateDirectory)) {
                $exitCode = $runner->run($application->consoleCommand('lint:twig', $templateDirectory, ...$twigOptions), $environment) ?: $exitCode;
            }
        }

        return $exitCode;
    }

    private function findDeployedEnvironment(TestApplication $application): string
    {
        foreach (self::DEPLOYED_ENVIRONMENTS as $environment) {
            if (is_dir($application->directory . '/config/packages/' . $environment)) {
                return $environment;
            }
        }

        return self::DEPLOYED_ENVIRONMENTS[0];
    }

    private function isBundle(TestApplication $application, string $packageDirectory): bool
    {
        if (!is_file($packageDirectory . '/composer.json')) {
            return false;
        }

        $packageName = json_decode((string) file_get_contents($packageDirectory . '/composer.json'), true, flags: JSON_THROW_ON_ERROR)['name'] ?? '';

        return $packageName !== '' && is_dir($application->directory . '/vendor/' . $packageName);
    }

    private function runTool(ProcessRunner $runner, TestApplication $application, string $tool, string $configurationFile, bool $writeBaseline): int
    {
        if (!is_file($application->directory . '/vendor/bin/' . $tool)) {
            throw new RuntimeException(sprintf('%s is configured but not installed. The package requires it in require-dev.', $tool));
        }

        $environment = $application->consoleEnvironment();

        if ($tool === 'phpstan') {
            // PHPStan reads the debug container. The warmup compiles it only when the configuration changed.
            $runner->mustRun($application->consoleCommand('cache:warmup', '-q'), [...$environment, 'APP_DEBUG' => '1']);
        }

        $baselineArguments = $writeBaseline ? ['--generate-baseline=' . dirname($configurationFile) . '/phpstan-baseline.neon'] : [];

        return $runner->run(match ($tool) {
            'phpstan' => $application->vendorBinaryCommand('phpstan', 'analyse', '-c', $configurationFile, ...$baselineArguments),
            'deptrac' => $application->vendorBinaryCommand('deptrac', 'analyse', '-c', $configurationFile),
            'phparkitect' => $application->vendorBinaryCommand('phparkitect', 'check', '--config=' . $configurationFile),
            default => throw new LogicException(sprintf('There is no tool called %s.', $tool)),
        }, $environment);
    }
}
