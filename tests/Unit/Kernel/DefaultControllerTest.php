<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Unit\Kernel;

use OpenDxp\TestFoundation\Controller\DefaultController;
use OpenDxp\TestFoundation\Kernel\CompilerPass\UseFoundationControllerWithoutApplication;
use OpenDxp\TestFoundation\Tests\Application\Controller\PageController;
use Symfony\Component\DependencyInjection\ContainerBuilder;

function defaultControllerAfterPass(string $configured): string
{
    $container = new ContainerBuilder();
    $container->setParameter('opendxp.documents.default_controller', $configured);

    (new UseFoundationControllerWithoutApplication())->process($container);

    return $container->getParameter('opendxp.documents.default_controller');
}

it('keeps the default controller of the application', function () {
    expect(defaultControllerAfterPass(PageController::class . '::defaultAction'))
        ->toBe(PageController::class . '::defaultAction');
});

it('uses the controller of the foundation when the application has none', function () {
    expect(defaultControllerAfterPass('App\Controller\DefaultController::defaultAction'))
        ->toBe(DefaultController::class . '::defaultAction');
});
