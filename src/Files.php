<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use RuntimeException;

final class Files
{
    public static function create(string $name, int $megabytes = 1): string
    {
        $path = self::directory() . '/' . $name;

        if (is_file($path)) {
            return $path;
        }

        $file = fopen($path, 'w');

        if ($file === false) {
            throw new RuntimeException(sprintf('Could not write a test file to %s.', $path));
        }

        fseek($file, $megabytes * 1_000_000 - 1);
        fwrite($file, "\0");
        fclose($file);

        return $path;
    }

    private static function directory(): string
    {
        $directory = OPENDXP_PRIVATE_VAR . '/tests';

        if (!is_dir($directory) && !mkdir($directory, recursive: true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create %s.', $directory));
        }

        return $directory;
    }
}
