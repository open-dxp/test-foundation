<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Feature;

use OpenDxp\Model\User;
use OpenDxp\TestFoundation\Admin;
use OpenDxp\TestFoundation\Container;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

it('hands the user it acts as to a service that asks who is logged in', function () {
    $user = new User();
    $user->setName('acting-as-' . uniqid());
    $user->save();

    Admin::actingAs($user);

    expect(Container::get(TokenStorageInterface::class)->getToken()?->getUser()?->getUser()->getId())->toBe($user->getId());
});
