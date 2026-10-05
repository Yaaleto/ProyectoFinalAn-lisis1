<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Usuario;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Cierra las sesiones ya abiertas de una cuenta o una organización que se desactivó (CA-01, RN-01).
 * El UserChecker solo actúa al iniciar sesión; esta revisión se repite en cada petición.
 */
final class ActiveAccountSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Después del firewall (prioridad 8), que es quien carga al usuario de la sesión.
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $usuario = $this->security->getUser();
        if (!$usuario instanceof Usuario) {
            return;
        }

        $motivo = match (true) {
            !$usuario->isActivo() => 'Esta cuenta está inactiva.',
            true !== $usuario->getOrganizacion()?->isActivo() => 'Esta organización está inactiva.',
            default => null,
        };
        if (null === $motivo) {
            return;
        }

        $this->security->logout(false);
        $session = $event->getRequest()->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('warning', $motivo.' Tu sesión se cerró.');
        }
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
    }
}
