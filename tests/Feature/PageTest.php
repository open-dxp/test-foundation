<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Feature\Page;

use LogicException;
use OpenDxp\Test\Factory\DocumentPageFactory;
use OpenDxp\TestFoundation\Page;
use OpenDxp\TestFoundation\Tests\Application\Controller\PageController;

beforeEach(function () {
    $this->path = DocumentPageFactory::new()
        ->withController(PageController::class, 'formAction')
        ->create()
        ->getFullPath();
});

it('reads the fields of a form by their labels', function () {
    $page = Page::open($this->path);

    expect($page->labels())
        ->toBe(['Name', 'Message', 'Color', 'Toppings', 'Newsletter', 'Small', 'Large', 'Bag', 'Gift', 'Birthday'])
        ->and($page->value('Name'))
        ->toBe('Ada')
        ->and($page->value('Message'))
        ->toBe('Hello')
        ->and($page->isChecked('Newsletter'))
        ->toBeTrue()
        ->and($page->description('Name'))
        ->toBe('As it stands in your passport');
});

it('reads the options of a list', function () {
    $page = Page::open($this->path);

    expect($page->options('Color'))
        ->toBe(['Please choose', 'Red', 'Blue'])
        ->and($page->selected('Color'))
        ->toBe(['Blue'])
        ->and($page->optionGroups('Color'))
        ->toBe([
            'Warm' => ['Red'],
            'Cold' => ['Blue'],
        ]);
});

it('sends what a person enters', function () {
    $page = Page::open($this->path)
        ->fillIn('Name', 'Grace')
        ->fillIn('Message', 'Hi')
        ->select('Color', 'Red')
        ->selectEach('Toppings', 'Cheese', 'Olives')
        ->pick('Size', 'Large')
        ->checkEach('Gift')
        ->selectParts('Birthday', ['month' => 'Jun'])
        ->fillInParts('Birthday', ['year' => 1983]);

    $page->press('Send');

    expect($page->notifications())
        ->toBe(['Thank you'])
        ->and($page->listItems('Sent'))
        ->toBe([
            'name: Grace',
            'message: Hi',
            'color: red',
            'toppings: cheese, olives',
            'newsletter: yes',
            'size: l',
            'extras: gift',
            'birthday: 6, 1983',
        ]);
});

it('counts how often the page shows a text', function () {
    expect(Page::open($this->path)->occurrences('passport'))->toBe(1);
});

it('reads the buttons of a page', function () {
    expect(Page::open($this->path)->buttons())->toBe(['Send']);
});

it('finds a link by its text', function () {
    $page = Page::open($this->path);

    expect($page->hasLink('Home'))->toBeTrue();
});

it('names the labels of the page when a field is missing', function () {
    expect(fn () => Page::open($this->path)->fillIn('Email', 'ada@example.com'))
        ->toThrow(LogicException::class, 'The page has 0 fields "Email". Its labels are: Name, Message');
});
