<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Feature;

use OpenDxp\TestFoundation\Command\AnalyseCommand;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function () {
    $this->package = sprintf('%s/analysed-package-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));

    // The function passes level 0 and fails level 9, because its parameter and its return have no type.
    $source = <<<'PHP'
        <?php

        function greet($name)
        {
            return $name;
        }
        PHP;

    $filesystem = new Filesystem();
    $filesystem->dumpFile($this->package . '/src/greeting.php', $source);
    $filesystem->dumpFile($this->package . '/phpstan.neon', phpstanConfiguration(level: 0));
    $filesystem->dumpFile($this->package . '/phpstan-strict.neon', phpstanConfiguration(level: 9));
});

afterEach(function () {
    (new Filesystem())->remove($this->package);
});

function phpstanConfiguration(int $level): string
{
    return <<<NEON
        parameters:
            level: {$level}
            paths:
                - src
        NEON;
}

/**
 * @param list<string> $phpstanConfigurations
 */
function listPhpstanConfigurations(string $package, array $phpstanConfigurations): void
{
    $manifest = [
        'name' => 'acme/analysed-package',
        'extra' => [
            'opendxp-test' => [
                'phpstan' => $phpstanConfigurations,
            ],
        ],
    ];

    (new Filesystem())->dumpFile($package . '/composer.json', json_encode($manifest, JSON_THROW_ON_ERROR));
}

it('runs only phpstan.neon when the package lists no PHPStan configuration', function () {
    $command = new CommandTester(new AnalyseCommand());

    $command->execute(['package' => $this->package, 'check' => 'phpstan']);

    expect($command->getStatusCode())->toBe(Command::SUCCESS);
});

it('runs every PHPStan configuration the package lists', function () {
    listPhpstanConfigurations($this->package, ['phpstan.neon', 'phpstan-strict.neon']);

    $command = new CommandTester(new AnalyseCommand());

    $command->execute(['package' => $this->package, 'check' => 'phpstan']);

    expect($command->getStatusCode())->toBe(Command::FAILURE);
});

it('leaves out phpstan.neon when the list does not name it', function () {
    $filesystem = new Filesystem();
    $filesystem->dumpFile($this->package . '/phpstan.neon', phpstanConfiguration(level: 9));
    $filesystem->dumpFile($this->package . '/phpstan-lenient.neon', phpstanConfiguration(level: 0));

    listPhpstanConfigurations($this->package, ['phpstan-lenient.neon']);

    $command = new CommandTester(new AnalyseCommand());

    $command->execute(['package' => $this->package, 'check' => 'phpstan']);

    expect($command->getStatusCode())->toBe(Command::SUCCESS);
});

it('writes the baseline of a configuration under its own name', function () {
    listPhpstanConfigurations($this->package, ['phpstan-strict.neon']);

    $command = new CommandTester(new AnalyseCommand());

    $command->execute(['package' => $this->package, '--baseline' => true]);

    expect($this->package . '/phpstan-strict-baseline.neon')
        ->toBeFile()
        ->and($this->package . '/phpstan-baseline.neon')
        ->not->toBeFile();
});

it('refuses a listed PHPStan configuration that does not exist', function () {
    listPhpstanConfigurations($this->package, ['phpstan-missing.neon']);

    $command = new CommandTester(new AnalyseCommand());

    expect(fn () => $command->execute(['package' => $this->package, 'check' => 'phpstan']))
        ->toThrow(RuntimeException::class, 'extra.opendxp-test.phpstan lists phpstan-missing.neon.');
});
