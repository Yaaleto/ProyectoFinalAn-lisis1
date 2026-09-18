<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\Form\UsuarioType;
use App\Repository\UsuarioRepository;
use App\Security\FincaAccess;
use App\Security\UsuarioPasswordManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SortDirection;

final class UsuarioController extends AbstractController
{
    #[Route('/users', name: 'app_user_index', methods: ['GET'])]
    public function index(UsuarioRepository $usuarioRepository): Response
    {
        if ($redirect = $this->redirectWhenCannotAdminister()) {
            return $redirect;
        }

        $administrador = $this->getAdministrator();
        $organizacion = $administrador->getOrganizacion();
        if (null === $organizacion) {
            throw $this->createAccessDeniedException();
        }

        $usuarios = $usuarioRepository->createOrganizationQueryBuilder($organizacion)
            ->orderBy('usuario.nombre', SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $this->render('user/index.html.twig', ['usuarios' => $usuarios]);
    }

    #[Route('/users/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UsuarioPasswordManager $passwordManager,
    ): Response {
        if ($redirect = $this->redirectWhenCannotAdminister()) {
            return $redirect;
        }

        $administrador = $this->getAdministrator();
        $organizacion = $administrador->getOrganizacion();
        if (null === $organizacion) {
            throw $this->createAccessDeniedException();
        }

        $usuario = (new Usuario('', '', '', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setCreadoPor($administrador)
            ->setActualizadoPor($administrador);
        $form = $this->createForm(UsuarioType::class, $usuario, [
            'organization' => $organizacion,
            'require_password' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->removeFarmFromAdministrator($usuario);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $passwordManager->setPassword($usuario, (string) $form->get('plainPassword')->getData());
            $entityManager->persist($usuario);
            $entityManager->flush();
            $this->addFlash('success', 'El usuario fue creado correctamente.');

            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/form.html.twig', [
            'form' => $form,
            'page_title' => 'Nuevo usuario',
            'submit_label' => 'Crear usuario',
        ]);
    }

    #[Route('/users/{usuario}/edit', name: 'app_user_edit', methods: ['GET', 'POST'], requirements: ['usuario' => '\\d+'])]
    public function edit(
        Usuario $usuario,
        Request $request,
        EntityManagerInterface $entityManager,
        UsuarioPasswordManager $passwordManager,
        FincaAccess $fincaAccess,
    ): Response {
        if ($redirect = $this->redirectWhenCannotAdminister()) {
            return $redirect;
        }

        $administrador = $this->getAdministrator();
        $organizacion = $administrador->getOrganizacion();
        $organizacionDelUsuario = $usuario->getOrganizacion();
        if (
            null === $organizacion
            || null === $organizacionDelUsuario
            || !$fincaAccess->canAccessOrganization($administrador, $organizacionDelUsuario)
        ) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(UsuarioType::class, $usuario, ['organization' => $organizacion]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->removeFarmFromAdministrator($usuario);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            if ('' !== $plainPassword) {
                $passwordManager->setPassword($usuario, $plainPassword);
            }

            $usuario->setActualizadoPor($administrador);
            $entityManager->flush();
            $this->addFlash('success', 'El usuario fue actualizado correctamente.');

            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/form.html.twig', [
            'form' => $form,
            'page_title' => 'Editar usuario',
            'submit_label' => 'Guardar cambios',
        ]);
    }

    private function getAdministrator(): Usuario
    {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        return $usuario;
    }

    private function redirectWhenCannotAdminister(): ?RedirectResponse
    {
        if ($this->isGranted('ROLE_ADMINISTRADOR')) {
            return null;
        }

        $this->addFlash('warning', 'Tu cuenta no tiene permiso para administrar usuarios.');

        return $this->redirectToRoute('app_health');
    }

    private function removeFarmFromAdministrator(Usuario $usuario): void
    {
        if (Usuario::ROL_ADMINISTRADOR === $usuario->getRol()) {
            $usuario->setFincaActiva(null);
        }
    }
}
