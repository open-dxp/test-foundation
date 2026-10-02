<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Application;

final class EnvironmentFile
{
    private const string TEMPLATE = 'vendor/open-dxp/test-foundation/app/.env.dist';

    /**
     * Takes every variable of the foundation's template from the environment, or keeps its default.
     */
    public static function writeFromTemplate(string $applicationDirectory): void
    {
        $lines = [];

        foreach (file($applicationDirectory . '/' . self::TEMPLATE, FILE_IGNORE_NEW_LINES) ?: [] as $templateLine) {
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

        file_put_contents($applicationDirectory . '/.env', implode("\n", $lines) . "\n");
    }
}
