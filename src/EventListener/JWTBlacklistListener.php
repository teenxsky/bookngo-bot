<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\UsersService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @psalm-suppress UnusedClass
 */
class JWTBlacklistListener
{
    public function __construct(
        private UsersService $usersService
    ) {
    }

    public function onJwtDecoded(JWTAuthenticatedEvent $event): void
    {
        $payload = $event->getPayload();

        if (!isset($payload['version'])) {
            throw new AccessDeniedException();
        }

        if (!$this->usersService->isValidToken(
            $payload['version'],
            $event->getToken()->getUser()->getUserIdentifier()
        )) {
            throw new AccessDeniedException();
        }
    }
}
