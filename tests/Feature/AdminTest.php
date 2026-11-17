<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Feature;

use OpenDxp\Model\User;
use OpenDxp\Security\User\UserLoader;
use OpenDxp\TestFoundation\Admin;
use OpenDxp\TestFoundation\Container;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

it('hands the user it acts as to a service that asks who is logged in', function () {
    $user = new User();
    $user->setName('acting-as-' . uniqid());
    $user->save();

    Admin::actingAs($user);

    $actingAs = Container::get(TokenStorageInterface::class)->getToken()?->getUser()?->getUser();

    expect($actingAs?->getId())->toBe($user->getId());
});

it('grants the roles of the user it acts as', function () {
    $user = new User();
    $user->setName('acting-as-' . uniqid());
    $user->setAdmin(true);
    $user->save();

    Admin::actingAs($user);

    expect(Container::get(TokenStorageInterface::class)->getToken()?->getRoleNames())
        ->toBe(['ROLE_OPENDXP_ADMIN']);
});

it('hands another user to the user loader when the test acts as them', function () {
    $first = new User();
    $first->setName('acting-as-' . uniqid());
    $first->save();
    $second = new User();
    $second->setName('acting-as-' . uniqid());
    $second->save();

    Admin::actingAs($first);
    Container::get(UserLoader::class)->getUser();
    Admin::actingAs($second);

    expect(Container::get(UserLoader::class)->getUser()?->getId())->toBe($second->getId());
});
