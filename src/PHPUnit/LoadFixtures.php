<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\PHPUnit;

use OpenDxp\TestFoundation\Application\ProcessRunner;
use OpenDxp\TestFoundation\Application\TestApplication;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Loads Foundry stories by their fixture names before the first test. Every test starts from them.
 */
final class LoadFixtures implements Extension
{
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        $fixtures = $parameters->has('fixtures') ? explode(',', $parameters->get('fixtures')) : [];
        $fixtures = array_values(array_filter(
            array_map(trim(...), $fixtures),
            static fn (string $fixture): bool => $fixture !== '',
        ));

        if ($fixtures === []) {
            return;
        }

        $application = TestApplication::fromWorkingDirectory();
        $runner = new ProcessRunner(new ConsoleOutput(), $application->directory);

        // A process of its own leaves no story and no kernel behind in the statics of the tests.
        $runner->mustRun(
            $application->consoleCommand('foundry:load-fixtures', '--append', ...$fixtures),
            $application->consoleEnvironment(),
        );
    }
}
