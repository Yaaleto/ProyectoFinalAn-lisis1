<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\Entity\Vacuna;
use App\Form\VacunaType;
use App\Repository\VacunaRepository;
use App\Security\FincaAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VacunaController extends AbstractController
{
    #[Route('/vaccines', name: 'app_vaccine_index', methods: ['GET'])]
    public function index(VacunaRepository $vacunaRepository): Response
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
            ? $vacunaRepository->createOrganizationQueryBuilder($organizacion)
            : $vacunaRepository->createActiveOrganizationQueryBuilder($organizacion);
        $vacunas = $queryBuilder
            ->orderBy('vacuna.nombre')
            ->getQuery()
            ->getResult();

        return $this->render('vaccine/index.html.twig', compact('vacunas', 'canManage'));
    }

    #[Route('/vaccines/new', name: 'app_vaccine_new', methods: ['GET', 'POST'])]
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

        $vacuna = (new Vacuna($organizacion, ''))
            ->setCreadoPor($administrador)
            ->setActualizadoPor($administrador);
        $form = $this->createForm(VacunaType::class, $vacuna);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($vacuna);
            $entityManager->flush();
            $this->addFlash('success', 'La vacuna fue creada correctamente.');

            return $this->redirectToRoute('app_vaccine_index');
        }

        return $this->render('vaccine/form.html.twig', [
            'form' => $form,
            'page_title' => 'Nueva vacuna',
            'submit_label' => 'Crear vacuna',
        ]);
    }

    #[Route('/vaccines/{vacuna}/edit', name: 'app_vaccine_edit', methods: ['GET', 'POST'], requirements: ['vacuna' => '\\d+'])]
    public function edit(
        Vacuna $vacuna,
        Request $request,
        EntityManagerInterface $entityManager,
        FincaAccess $fincaAccess,
    ): Response {
        if ($redirect = $this->redirectWhenCannotAdminister()) {
            return $redirect;
        }

        $administrador = $this->getAdministrator();
        if (!$fincaAccess->canAccessOrganization($administrador, $vacuna->getOrganizacion())) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(VacunaType::class, $vacuna);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $vacuna->setActualizadoPor($administrador);
            $entityManager->flush();
            $this->addFlash('success', 'La vacuna fue actualizada correctamente.');

            return $this->redirectToRoute('app_vaccine_index');
        }

        return $this->render('vaccine/form.html.twig', [
            'form' => $form,
            'page_title' => 'Editar vacuna',
            'submit_label' => 'Guardar cambios',
        ]);
    }

    #[Route('/vaccines/{vacuna}/toggle-status', name: 'app_vaccine_toggle_status', methods: ['POST'], requirements: ['vacuna' => '\\d+'])]
    public function toggleStatus(
        Vacuna $vacuna,
        Request $request,
        EntityManagerInterface $entityManager,
        FincaAccess $fincaAccess,
    ): Response {
        if ($redirect = $this->redirectWhenCannotAdminister()) {
            return $redirect;
        }

        $administrador = $this->getAdministrator();
        if (!$fincaAccess->canAccessOrganization($administrador, $vacuna->getOrganizacion())) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('toggle-vaccine-'.$vacuna->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $vacuna->setActiva(!$vacuna->isActiva())
            ->setActualizadoPor($administrador);
        $entityManager->flush();
        $this->addFlash('success', $vacuna->isActiva() ? 'La vacuna fue activada correctamente.' : 'La vacuna fue desactivada correctamente.');

        return $this->redirectToRoute('app_vaccine_index');
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

        $this->addFlash('warning', 'Tu cuenta no tiene permiso para administrar vacunas.');

        return $this->redirectToRoute('app_health');
    }
}
