<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Feature;

use OpenDxp\Model\Document;

it('starts from the stories that phpunit.xml names', function () {
    expect(Document::getByPath('/preloaded-page'))->toBeInstanceOf(Document\Page::class);
});
