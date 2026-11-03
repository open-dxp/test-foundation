<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Application;

use Composer\InstalledVersions;
use DOMXPath;
use RuntimeException;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

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
        $environmentFile = Path::join($directory, '.env');

        if ((new Filesystem())->exists($environmentFile)) {
            (new Dotenv())->loadEnv($environmentFile);
        }

        return new self($directory, self::readKernelClass($directory));
    }

    public static function workingDirectory(): string
    {
        return getcwd() ?: throw new RuntimeException('The working directory cannot be read.');
    }

    public static function readKernelClass(string $directory): string
    {
        $filesystem = new Filesystem();

        foreach (['phpunit.xml', 'phpunit.xml.dist'] as $file) {
            $configuration = Path::join($directory, $file);

            if (!$filesystem->exists($configuration)) {
                continue;
            }

            $kernelClass = (new DOMXPath(XmlUtils::loadFile($configuration)))
                ->evaluate('string(/phpunit/php/*[@name="KERNEL_CLASS"]/@value)');

            if (is_string($kernelClass) && $kernelClass !== '') {
                return $kernelClass;
            }
        }

        throw new RuntimeException(sprintf('%s names no KERNEL_CLASS in phpunit.xml.dist.', $directory));
    }

    /**
     * The namespaces of the migrations the package under test ships. A bundle is tested inside an application that
     * names it in composer.json. A project is the application and names no package, so it has none.
     *
     * @return list<string>
     */
    public function migrationNamespacesOfPackageUnderTest(): array
    {
        $package = $this->packageUnderTest();

        if ($package === null) {
            return [];
        }

        $directory = InstalledVersions::getInstallPath($package)
            ?? throw new RuntimeException(sprintf('The package under test %s is not installed.', $package));

        $sources = array_filter(
            [Path::join($directory, 'src'), Path::join($directory, 'lib')],
            (new Filesystem())->exists(...),
        );

        if ($sources === []) {
            return [];
        }

        $migrations = Finder::create()
            ->files()
            ->in($sources)
            ->depth('<= 3')
            ->path('#(^|/)Migrations/Version[^/]*\.php$#');

        $namespaces = [];

        foreach ($migrations as $migration) {
            if (preg_match('/^namespace ([^;]+);/m', $migration->getContents(), $match)) {
                $namespaces[$match[1]] = true;
            }
        }

        return array_keys($namespaces);
    }

    public function isProject(): bool
    {
        return $this->packageUnderTest() === null;
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

    private function packageUnderTest(): ?string
    {
        $manifest = json_decode(
            (new Filesystem())->readFile(Path::join($this->directory, 'composer.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $manifest['extra']['opendxp-test']['package'] ?? null;
    }
}
