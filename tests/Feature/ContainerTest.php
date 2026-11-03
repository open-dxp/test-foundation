<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Feature;

use OpenDxp\Localization\LocaleServiceInterface;
use OpenDxp\TestFoundation\Container;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

it('hands the locale of a request of the test client to OpenDXP', function () {
    $locale = null;
    $dispatcher = Container::get('event_dispatcher');
    $dispatcher->addListener(
        KernelEvents::REQUEST,
        static fn (RequestEvent $event) => $event->getRequest()->setLocale('de'),
        1000,
    );
    $dispatcher->addListener(
        KernelEvents::REQUEST,
        static function () use (&$locale): void {
            $locale = Container::get(LocaleServiceInterface::class)->findLocale();
        },
        -1000,
    );

    Container::testClient()->request('GET', '/');

    expect($locale)->toBe('de');
});
