<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Feature;

use LogicException;
use OpenDxp\TestFoundation\Concurrency;

it('refuses to run in a test with rollback', function () {
    Concurrency::run(fn () => true);
})->throws(LogicException::class, 'Concurrency::run() needs a test that extends WithoutRollbackTestCase.');
