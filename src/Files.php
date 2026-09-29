<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use RuntimeException;

final class Files
{
    /**
     * A file of a given size, and the path to it.
     *
     * The bytes are nothing in particular: this is for tests about what an application does with
     * a file of that size, not about what is in it. It is a sparse file, so twenty five megabytes
     * cost no disk and no time. Handing the same name back twice hands back the same file.
     */
    public static function sized(string $name, int $megabytes = 1): string
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
