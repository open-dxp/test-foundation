<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Application;

use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final readonly class ProcessRunner
{
    public function __construct(
        private OutputInterface $output,
        private string $workingDirectory,
    ) {
    }

    /**
     * Prints the output only when the command fails or the run is verbose.
     *
     * @param list<string>          $command
     * @param array<string, string> $environment
     */
    public function mustRun(array $command, array $environment = []): void
    {
        $collectedOutput = '';

        $exitCode = $this->createProcess($command, $environment)->run(function (string $type, string $buffer) use (&$collectedOutput): void {
            $collectedOutput .= $buffer;

            if ($this->output->isVerbose()) {
                $this->output->write($buffer);
            }
        });

        if ($exitCode === 0) {
            return;
        }

        if (!$this->output->isVerbose()) {
            $this->output->write($collectedOutput);
        }

        throw new RuntimeException(sprintf('Failed: %s', implode(' ', $command)));
    }

    /**
     * @param list<string>          $command
     * @param array<string, string> $environment
     */
    public function run(array $command, array $environment = []): int
    {
        return $this->createProcess($command, $environment)->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
    }

    /**
     * @param list<string>          $command
     * @param array<string, string> $environment
     */
    private function createProcess(array $command, array $environment): Process
    {
        return new Process($command, $this->workingDirectory, $environment, null, null);
    }
}
