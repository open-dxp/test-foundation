<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use Closure;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use LogicException;
use OpenDxp\Cache\RuntimeCache;
use OpenDxp\Db;
use Spatie\Fork\Fork;

final class Concurrency
{
    /**
     * Runs the closures in parallel processes and returns their results in the order of the closures.
     * Each process starts like a new request with its own kernel, database connection and session.
     *
     * @return list<mixed>
     */
    public static function run(Closure ...$closures): array
    {
        // DAMA keeps one static connection while it rolls back tests. All processes would share it.
        if (StaticDriver::isKeepStaticConnections()) {
            throw new LogicException('Concurrency::run() needs a test that extends WithoutRollbackTestCase.');
        }

        // PHP locks the session file of the test until the session closes.
        session_write_close();

        $results = Fork::new()
            ->before(
                child: self::startRequest(...),
                // A child process that reboots its kernel also closes the connection it shares with the test.
                parent: static fn () => Db::get()->close(),
            )
            ->run(...$closures);

        // Fork collects the results in the order in which the processes finish.
        ksort($results);

        return $results;
    }

    private static function startRequest(): void
    {
        session_id(session_create_id());
        RuntimeCache::clear();
        Container::get('kernel')->reboot(null);
    }
}
