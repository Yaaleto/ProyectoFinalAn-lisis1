<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Raza;
use App\Entity\Usuario;
use App\Form\RazaType;
use App\Repository\RazaRepository;
use App\Security\FincaAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RazaController extends AbstractController
{
    #[Route('/breeds', name: 'app_breed_index', methods: ['GET'])]
    public function index(RazaRepository $razaRepository): Response
    {
        $usuario = $this->getCatalogUser();
        $organizacion = $usuario->getOrganizacion();
        if (null === $organizacion) {
            throw $this->createAccessDeniedException();
        }

        $canManage = $this->isGranted('ROLE_ADMINISTRADOR');
        if (!$canManage && !$this->isGranted('ROLE_ENCARGADO')) {
            throw $this->createAccessDeniedException();
        }

        $queryBuilder = $canManage
            ? $razaRepository->createOrganizationQueryBuilder($organizacion)
            : $razaRepository->createActiveOrganizationQueryBuilder($organizacion);
        $razas = $queryBuilder
            ->orderBy('raza.nombre')
            ->getQuery()
            ->getResult();

        return $this->render('breed/index.html.twig', compact('razas', 'canManage'));
    }

    #[Route('/breeds/new', name: 'app_breed_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($redirect = $this->redirectWhenCannotAdminister()) {
            return $redirect;
        }

        $administrador = $this->getAdministrator();
        $organizacion = $administrador->getOrganizacion();
        if (null === $organizacion) {
            throw $this->createAccessDeniedException();
        }

        $raza = (new Raza($organizacion, ''))
            ->setCreadoPor($administrador)
            ->setActualizadoPor($administrador);
        $form = $this->createForm(RazaType::class, $raza);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($raza);
            $entityManager->flush();
            $this->addFlash('success', 'La raza fue creada correctamente.');

            return $this->redirectToRoute('app_breed_index');
        }

        return $this->render('breed/form.html.twig', [
            'form' => $form,
            'page_title' => 'Nueva raza',
            'submit_label' => 'Crear raza',
        ]);
    }

    #[Route('/breeds/{raza}/edit', name: 'app_breed_edit', methods: ['GET', 'POST'], requirements: ['raza' => '\\d+'])]
    public function edit(
        Raza $raza,
        Request $request,
        EntityManagerInterface $entityManager,
        FincaAccess $fincaAccess,
    ): Response {
        if ($redirect = $this->redirectWhenCannotAdminister()) {
            return $redirect;
        }

        $administrador = $this->getAdministrator();
        if (!$fincaAccess->canAccessOrganization($administrador, $raza->getOrganizacion())) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(RazaType::class, $raza);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $raza->setActualizadoPor($administrador);
            $entityManager->flush();
            $this->addFlash('success', 'La raza fue actualizada correctamente.');

            return $this->redirectToRoute('app_breed_index');
        }

        return $this->render('breed/form.html.twig', [
            'form' => $form,
            'page_title' => 'Editar raza',
            'submit_label' => 'Guardar cambios',
        ]);
    }

    #[Route('/breeds/{raza}/toggle-status', name: 'app_breed_toggle_status', methods: ['POST'], requirements: ['raza' => '\\d+'])]
    public function toggleStatus(
        Raza $raza,
        Request $request,
        EntityManagerInterface $entityManager,
        FincaAccess $fincaAccess,
    ): Response {
        if ($redirect = $this->redirectWhenCannotAdminister()) {
            return $redirect;
        }

        $administrador = $this->getAdministrator();
        if (!$fincaAccess->canAccessOrganization($administrador, $raza->getOrganizacion())) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('toggle-breed-'.$raza->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $raza->setActiva(!$raza->isActiva())
            ->setActualizadoPor($administrador);
        $entityManager->flush();
        $this->addFlash('success', $raza->isActiva() ? 'La raza fue activada correctamente.' : 'La raza fue desactivada correctamente.');

        return $this->redirectToRoute('app_breed_index');
    }

    private function getAdministrator(): Usuario
    {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        return $usuario;
    }

    private function getCatalogUser(): Usuario
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

        $this->addFlash('warning', 'Tu cuenta no tiene permiso para administrar razas.');

        return $this->redirectToRoute('app_health');
    }
}
