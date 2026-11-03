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
     * Logs the user in for the services the test calls, the same way a backend request does.
     *
     * A request through the browser names its user with Browser::actingAs() instead.
     */
    public static function actingAs(User $user): void
    {
        $securityUser = new SecurityUser($user);

        Container::get(TokenStorageInterface::class)->setToken(
            new UsernamePasswordToken($securityUser, self::FIREWALL, $securityUser->getRoles()),
        );
    }
}
