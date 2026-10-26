<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Application;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

final readonly class ProjectDefinitions
{
    private const array PATHS = [
        '/^definition_[^\/]+\.php$/',
        '/^fieldcollections\/[^\/]+\.php$/',
        '/^objectbricks\/[^\/]+\.php$/',
    ];

    /**
     * @param array<string, string> $contents the content of each definition file by its path
     */
    private function __construct(
        private array $contents,
    ) {
    }

    public static function capture(string $applicationDirectory): self
    {
        $classesDirectory = $applicationDirectory . '/var/classes';

        if (!(new Filesystem())->exists($classesDirectory)) {
            return new self([]);
        }

        $contents = [];

        foreach ((new Finder())->files()->in($classesDirectory)->path(self::PATHS) as $file) {
            $contents[$file->getPathname()] = $file->getContents();
        }

        return new self($contents);
    }

    public function restore(): void
    {
        $filesystem = new Filesystem();

        foreach ($this->contents as $path => $content) {
            $filesystem->dumpFile($path, $content);
        }
    }
}
