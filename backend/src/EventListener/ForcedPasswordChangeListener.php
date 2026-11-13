<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Http\UserFacingHttpException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * After an admin reset the temporary password is only good for choosing a new one.
 * Everything else stays closed until that has happened.
 */
#[AsEventListener]
final readonly class ForcedPasswordChangeListener
{
    /** Seeing who one is, setting the new password, and leaving. */
    private const array ALLOWED_ROUTES = ['api_me_get', 'api_me_password', '_logout_api', 'gesdinet_jwt_refresh_token', 'api_health'];

    public function __construct(private Security $security)
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest() || \in_array($event->getRequest()->attributes->get('_route'), self::ALLOWED_ROUTES, true)) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User && $user->mustChangePassword()) {
            throw UserFacingHttpException::forbidden('Dein Passwort wurde zurückgesetzt. Bitte wähle zuerst ein neues Passwort.');
        }
    }
}
