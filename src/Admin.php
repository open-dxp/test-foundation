<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use OpenDxp\Model\User;
use OpenDxp\Security\User\User as SecurityUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class Admin
{
    public const string FIREWALL = 'opendxp_admin';

    /**
     * A service that asks which backend user is logged in is then handed this user, the same way a backend request
     * would hand it one. A request through the browser names its user with Browser::actingAs() instead.
     */
    public static function actingAs(User $user): void
    {
        Container::get(TokenStorageInterface::class)->setToken(
            new UsernamePasswordToken(new SecurityUser($user), self::FIREWALL, $user->getRoles()),
        );
    }
}
