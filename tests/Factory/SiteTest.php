<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Factory;

use OpenDxp\Model\Document\Page;
use OpenDxp\Model\Site;
use OpenDxp\TestFoundation\Factory\PageFactory;
use OpenDxp\TestFoundation\Factory\SiteFactory;

it('brings a root document named after its domain', function () {
    $site = SiteFactory::createOne(['mainDomain' => 'test-domain1.test']);

    expect($site)->toBeInstanceOf(Site::class)
        ->and($site->getId())->toBeGreaterThan(0)
        ->and($site->getRootDocument())->toBeInstanceOf(Page::class)
        ->and($site->getRootDocument()->getKey())->toBe('test-domain1-test');
});

it('serves from a root document the caller built', function () {
    $root = PageFactory::new()->inLocale('de')->create(['key' => 'de-root']);
    $site = SiteFactory::new()->rootedAt($root)->create(['mainDomain' => 'test-domain2.test']);

    expect($site->getRootId())->toBe($root->getId())
        ->and($site->getRootDocument()->getProperty('language'))->toBe('de');
});

it('carries the settings and the further domains a test gives it', function () {
    $site = SiteFactory::new()
        ->settings(['i18n' => ['zone' => 'zone1']])
        ->alsoAt(['www.test-domain3.test'])
        ->create(['mainDomain' => 'test-domain3.test']);

    expect($site->getCustomSettings())->toBe(['i18n' => ['zone' => 'zone1']])
        ->and($site->getDomains())->toBe(['www.test-domain3.test']);
});

it('gives every site a domain of its own when none is named', function () {
    $domains = array_map(static fn (Site $site) => $site->getMainDomain(), SiteFactory::createMany(3));

    expect(array_unique($domains))->toHaveCount(3);
});
