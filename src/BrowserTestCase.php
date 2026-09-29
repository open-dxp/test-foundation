<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use Zenstruck\Browser\Test\HasBrowser;

abstract class BrowserTestCase extends TestCase
{
    use HasBrowser;

    protected function setUp(): void
    {
        parent::setUp();

        Browser::providedBy(fn () => $this->playwrightBrowser());
    }
}
