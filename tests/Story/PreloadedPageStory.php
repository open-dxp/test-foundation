<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Story;

use OpenDxp\Test\Factory\DocumentPageFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'preloaded-page')]
final class PreloadedPageStory extends Story
{
    public function build(): void
    {
        DocumentPageFactory::createOne(['key' => 'preloaded-page']);
    }
}
