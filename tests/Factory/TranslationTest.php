<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Factory;

use OpenDxp\Model\Translation;
use OpenDxp\TestFoundation\Factory\TranslationFactory;

it('writes a translated key', function () {
    $translation = TranslationFactory::new()
        ->saying(['en' => 'Read more', 'de' => 'Mehr erfahren'])
        ->create(['key' => 'teaser.more']);

    expect($translation)->toBeInstanceOf(Translation::class)
        ->and(Translation::getByKey('teaser.more')->getTranslation('de'))->toBe('Mehr erfahren');
})->skip(
    'Translation\Dao::save() issues CREATE TABLE IF NOT EXISTS on every write. MySQL commits on '
    . 'any DDL, which ends the transaction the tests are isolated by, so everything after this '
    . 'test would be written for real. The factory works; OpenDXP has to stop doing that first.'
);
