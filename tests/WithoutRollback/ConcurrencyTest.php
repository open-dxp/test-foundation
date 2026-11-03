<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\WithoutRollback;

use OpenDxp\Db;
use OpenDxp\Model\DataObject;
use OpenDxp\TestFoundation\Concurrency;

it('runs the closures concurrently', function () {
    $interval = function (): array {
        $start = microtime(true);
        usleep(500_000);

        return [$start, microtime(true)];
    };

    [$first, $second] = Concurrency::run($interval, $interval);

    expect($second[0])->toBeLessThan($first[1]);
});

it('returns the results in the order of the closures', function () {
    $results = Concurrency::run(
        function (): string {
            usleep(300_000);

            return 'slow';
        },
        fn (): string => 'fast',
    );

    expect($results)->toBe([
        'slow',
        'fast',
    ]);
});

it('loads models from the database in each process', function () {
    $folder = DataObject\Folder::create([
        'key' => 'concurrency',
        'parentId' => 1,
    ]);

    Db::get()->executeStatement('UPDATE objects SET modificationDate = 1 WHERE id = ?', [$folder->getId()]);

    $dates = Concurrency::run(fn () => DataObject::getById($folder->getId())?->getModificationDate());

    expect($dates)->toBe([1]);
});

it('gives each process its own database connection', function () {
    $test = Db::get()->fetchOne('SELECT CONNECTION_ID()');

    $processes = Concurrency::run(
        fn () => Db::get()->fetchOne('SELECT CONNECTION_ID()'),
        fn () => Db::get()->fetchOne('SELECT CONNECTION_ID()'),
    );

    expect(array_unique([$test, ...$processes]))->toHaveCount(3);
});

it('gives each process its own session', function () {
    session_start();

    $sessions = Concurrency::run(
        function (): string {
            session_start();

            return session_id();
        },
        function (): string {
            session_start();

            return session_id();
        },
    );

    expect(array_unique([session_id(), ...$sessions]))->toHaveCount(3);
})->after(function () {
    session_start();
    session_destroy();
});
