<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Application;

use RuntimeException;
use Symfony\Component\Dotenv\Dotenv;

final readonly class TestApplication
{
    private function __construct(
        public string $directory,
        public string $kernelClass,
    ) {
    }

    public static function fromWorkingDirectory(): self
    {
        $directory = self::workingDirectory();

        if (is_file($directory . '/.env')) {
            (new Dotenv())->loadEnv($directory . '/.env');
        }

        return new self($directory, self::readKernelClass($directory));
    }

    public static function workingDirectory(): string
    {
        return getcwd() ?: throw new RuntimeException('The working directory cannot be read.');
    }

    public static function readKernelClass(string $directory): string
    {
        foreach (['phpunit.xml', 'phpunit.xml.dist'] as $file) {
            $configuration = $directory . '/' . $file;

            if (is_file($configuration) && preg_match('/name="KERNEL_CLASS" value="([^"]+)"/', (string) file_get_contents($configuration), $match)) {
                return $match[1];
            }
        }

        throw new RuntimeException(sprintf('%s names no KERNEL_CLASS in phpunit.xml.dist.', $directory));
    }

    /**
     * OpenDXP's console reads the kernel class from OPENDXP_KERNEL_CLASS, not from phpunit.xml.
     *
     * @return array<string, string>
     */
    public function consoleEnvironment(): array
    {
        return [
            'OPENDXP_PROJECT_ROOT' => $this->directory,
            'OPENDXP_KERNEL_CLASS' => $this->kernelClass,
        ];
    }

    /**
     * @return list<string>
     */
    public function consoleCommand(string ...$arguments): array
    {
        return [PHP_BINARY, 'bin/console', ...array_values($arguments)];
    }

    /**
     * @return list<string>
     */
    public function vendorBinaryCommand(string $binary, string ...$arguments): array
    {
        return [PHP_BINARY, 'vendor/bin/' . $binary, ...array_values($arguments)];
    }
}
