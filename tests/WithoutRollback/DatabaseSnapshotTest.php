<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\WithoutRollback;

use OpenDxp\Db;
use OpenDxp\Model\DataObject;
use OpenDxp\Test\Factory\DataObjectFolderFactory;
use OpenDxp\TestFoundation\Concurrency;
use OpenDxp\TestFoundation\DatabaseSnapshot;

it('removes the rows that the test added', function () {
    $folder = DataObjectFolderFactory::createOne();

    DatabaseSnapshot::restore();

    expect(DataObject::getById($folder->getId(), ['force' => true]))->toBeNull();
});

it('restores the rows that the test changed', function () {
    $modificationDate = Db::get()->fetchOne('SELECT modificationDate FROM objects WHERE id = 1');
    Db::get()->executeStatement('UPDATE objects SET modificationDate = 1 WHERE id = 1');

    DatabaseSnapshot::restore();

    expect(Db::get()->fetchOne('SELECT modificationDate FROM objects WHERE id = 1'))->toBe($modificationDate);
});

it('restores the rows when the test left a transaction open', function () {
    $modificationDate = Db::get()->fetchOne('SELECT modificationDate FROM objects WHERE id = 1');
    Db::get()->executeStatement('UPDATE objects SET modificationDate = 1 WHERE id = 1');
    Db::get()->beginTransaction();

    DatabaseSnapshot::restore();
    Db::get()->close();

    expect(Db::get()->fetchOne('SELECT modificationDate FROM objects WHERE id = 1'))->toBe($modificationDate);
});

it('removes the rows that a process added', function () {
    [$id] = Concurrency::run(fn () => DataObjectFolderFactory::createOne()->getId());

    DatabaseSnapshot::restore();

    expect(DataObject::getById($id, ['force' => true]))->toBeNull();
});
