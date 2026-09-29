<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Factory;

use OpenDxp\TestFoundation\Controller\DefaultController;
use OpenDxp\Model\Document;
use OpenDxp\Model\Document\Hardlink;
use OpenDxp\Model\Document\Link;
use OpenDxp\Model\Document\Page;
use OpenDxp\TestFoundation\Factory\HardlinkFactory;
use OpenDxp\TestFoundation\Factory\LinkFactory;
use OpenDxp\TestFoundation\Factory\PageFactory;
use OpenDxp\TestFoundation\Factory\SnippetFactory;

it('writes a document and gives it an id', function () {
    $page = PageFactory::createOne();

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->getId())->toBeGreaterThan(0)
        ->and(Page::getById($page->getId()))->toBeInstanceOf(Page::class);
});

it('takes the key the caller names', function () {
    expect(PageFactory::createOne(['key' => 'about-us'])->getKey())->toBe('about-us');
});

it('gives every document a key of its own when none is named', function () {
    $keys = array_map(static fn (Page $page) => $page->getKey(), PageFactory::createMany(3));

    expect(array_unique($keys))->toHaveCount(3);
});

it('hands back a document that was never written', function () {
    $page = PageFactory::new()->unsaved()->create();

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->getId())->toBeNull();
});

it('puts a document below another one', function () {
    $parent = PageFactory::createOne(['key' => 'en']);
    $child = PageFactory::new()->childOf($parent)->create(['key' => 'about-us']);

    expect($child->getParentId())->toBe($parent->getId())
        ->and($child->getFullPath())->toBe('/en/about-us');
});

it('marks the language a document belongs to', function () {
    $page = PageFactory::new()->inLocale('de_CH')->create();

    expect($page->getProperty('language'))->toBe('de_CH');
});

it('leaves a document unpublished when asked to', function () {
    expect(PageFactory::new()->unpublished()->create()->isPublished())->toBeFalse()
        ->and(PageFactory::createOne()->isPublished())->toBeTrue();
});

it('leaves the controller to the application unless a test names one', function () {
    $named = PageFactory::new()->controller(DefaultController::class, 'javascriptAction')->create();

    expect($named->getController())->toBe(DefaultController::class . '::javascriptAction')
        ->and(PageFactory::createOne()->getController())
        ->toBe(DefaultController::class . '::defaultAction');
});

it('writes a snippet', function () {
    expect(SnippetFactory::createOne()->getId())->toBeGreaterThan(0);
});

it('points a link at another document', function () {
    $target = PageFactory::createOne();
    $link = LinkFactory::new()->to($target)->create();

    expect($link)->toBeInstanceOf(Link::class)
        ->and($link->getInternal())->toBe($target->getId())
        ->and($link->getLinktype())->toBe('internal');
});

it('mirrors a document with a hardlink', function () {
    $source = PageFactory::createOne();
    $hardlink = HardlinkFactory::new()->to($source)->inLocale('de')->create();

    expect($hardlink)->toBeInstanceOf(Hardlink::class)
        ->and($hardlink->getSourceId())->toBe($source->getId())
        ->and($hardlink->getChildrenFromSource())->toBeTrue()
        ->and($hardlink->getProperty('language'))->toBe('de');
});

it('links a document as the language variant of another', function () {
    $en = PageFactory::new()->inLocale('en')->create(['key' => 'en']);
    $de = PageFactory::new()->inLocale('de')->translationOf($en)->create(['key' => 'de']);

    $variants = (new Document\Service())->getTranslations($en);

    expect($variants)->toHaveKey('de')
        ->and($variants['de'])->toBe($de->getId());
});

it('joins a third document to the same set of variants', function () {
    $en = PageFactory::new()->inLocale('en')->create(['key' => 'en-root']);
    $de = PageFactory::new()->inLocale('de')->translationOf($en)->create(['key' => 'de-root']);
    $fr = PageFactory::new()->inLocale('fr')->translationOf($de)->create(['key' => 'fr-root']);

    // Linking to any document of a set joins that set, and once there is one the source counts
    // as one of its own variants.
    $variants = (new Document\Service())->getTranslations($en);
    ksort($variants);

    expect($variants)->toBe(['de' => $de->getId(), 'en' => $en->getId(), 'fr' => $fr->getId()]);
});
