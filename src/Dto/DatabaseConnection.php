<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Dto;

use RuntimeException;

final readonly class DatabaseConnection
{
    private function __construct(
        public string $host,
        public int $port,
        public string $user,
        public string $password,
        public string $database,
    ) {
    }

    public static function fromUrl(string $url): self
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['host'], $parts['path'])) {
            throw new RuntimeException('DATABASE_URL names no database. Expected mysql://user:password@host:3306/name.');
        }

        return new self(
            $parts['host'],
            $parts['port'] ?? 3306,
            urldecode($parts['user'] ?? 'root'),
            urldecode($parts['pass'] ?? ''),
            ltrim($parts['path'], '/'),
        );
    }
}
