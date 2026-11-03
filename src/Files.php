<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use SplFileObject;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class Files
{
    public static function create(string $name, int $megabytes = 1): string
    {
        $filesystem = new Filesystem();
        $directory = Path::join(OPENDXP_PRIVATE_VAR, 'tests');
        $path = Path::join($directory, $name);

        if ($filesystem->exists($path)) {
            return $path;
        }

        $filesystem->mkdir($directory);

        // Writing only the last byte creates a sparse file, which costs neither time nor disk space.
        $file = new SplFileObject($path, 'w');
        $file->fseek($megabytes * 1_000_000 - 1);
        $file->fwrite("\0");

        return $path;
    }
}
