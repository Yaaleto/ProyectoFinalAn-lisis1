<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthenticationController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_health');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $response = $this->render('auth/login.html.twig', [
            'error' => $error,
            'csrfError' => $error instanceof InvalidCsrfTokenException,
            'lastUsername' => $authenticationUtils->getLastUsername(),
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): RedirectResponse
    {
        throw new \LogicException('This method is intercepted by Symfony logout.');
    }
}
