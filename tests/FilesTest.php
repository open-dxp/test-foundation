<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests;

use OpenDxp\TestFoundation\Files;

it('makes a file of the size a test asks for', function () {
    $path = Files::sized('upload.pdf', megabytes: 3);

    expect($path)->toBeFile()
        ->and(filesize($path))->toBe(3_000_000);
});

it('hands the same file back rather than writing it again', function () {
    expect(Files::sized('twice.bin'))->toBe(Files::sized('twice.bin'));
});
