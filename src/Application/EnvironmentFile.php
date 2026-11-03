<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Application;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class EnvironmentFile
{
    private const string TEMPLATE = 'vendor/open-dxp/test-foundation/app/.env.dist';

    /**
     * Takes every variable of the foundation's template from the environment, or keeps its default.
     */
    public static function writeFromTemplate(string $applicationDirectory): void
    {
        $filesystem = new Filesystem();
        $template = $filesystem->readFile(Path::join($applicationDirectory, self::TEMPLATE));
        $lines = [];

        foreach (explode("\n", $template) as $templateLine) {
            $name = strstr($templateLine, '=', true);

            if ($name === false || str_starts_with($templateLine, '#')) {
                continue;
            }

            $value = getenv($name);
            $lines[] = $value === false ? $templateLine : $name . '=' . $value;
        }

        $kernelClass = TestApplication::readKernelClass($applicationDirectory);
        $lines[] = 'KERNEL_CLASS=' . $kernelClass;
        $lines[] = 'OPENDXP_KERNEL_CLASS=' . $kernelClass;

        $filesystem->dumpFile(Path::join($applicationDirectory, '.env'), implode("\n", $lines) . "\n");
    }
}
