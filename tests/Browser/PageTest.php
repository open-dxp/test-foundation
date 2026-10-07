<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Browser\Page;

use OpenDxp\Test\Factory\DocumentPageFactory;
use OpenDxp\TestFoundation\Page;
use OpenDxp\TestFoundation\Tests\Application\Controller\PageController;

beforeEach(function () {
    $this->path = DocumentPageFactory::new()
        ->withController(PageController::class, 'formAction')
        ->create()
        ->getFullPath();
});

it('tells whether the browser shows a field', function (string $label, bool $visible) {
    expect(Page::inBrowser($this->path)->isVisible($label))->toBe($visible);
})->with([
    'a field it shows' => ['Name', true],
    'a field its style hides' => ['Secret', false],
]);

it('presses a key in the browser', function () {
    $page = Page::inBrowser($this->path)
        ->fillIn('Name', 'Grace')
        ->pressKey('Tab');

    expect($page->value('Name'))->toBe('Grace');
});

it('reads what a person selects in the browser', function () {
    $page = Page::inBrowser($this->path)->selectEach('Toppings', 'Cheese', 'Ham');

    expect($page->selected('Toppings'))->toBe(['Cheese', 'Ham']);
});

it('tells whether the browser shows a button', function (string $button, bool $shown) {
    expect(Page::inBrowser($this->path)->showsButton($button))->toBe($shown);
})->with([
    'a button it shows' => ['Send', true],
    'a button its style hides' => ['Reset', false],
]);

it('waits until the page shows a text a script adds later', function () {
    $page = Page::inBrowser($this->path)
        ->press('Copy')
        ->waitForText('Copi');

    expect($page->shows('Copied'))->toBeTrue();
});
