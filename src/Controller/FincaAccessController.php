<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Finca;
use App\Entity\Usuario;
use App\Form\FincaType;
use App\Repository\FincaRepository;
use App\Security\Voter\FincaVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FincaAccessController extends AbstractController
{
    #[Route('/farms', name: 'app_farm_index', methods: ['GET'])]
    public function index(FincaRepository $fincaRepository): Response
    {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        if (Usuario::ROL_ENCARGADO === $usuario->getRol()) {
            $fincaActiva = $usuario->getFincaActiva();
            if (null === $fincaActiva) {
                $this->addFlash('warning', 'Tu cuenta todavía no tiene una finca asignada.');

                return $this->redirectToRoute('app_health');
            }

            return $this->redirectToRoute('app_farm_show', ['finca' => $fincaActiva->getId()]);
        }

        if ($redirect = $this->redirectWhenCannotAdminister()) {
            return $redirect;
        }

        $fincas = $fincaRepository->createAuthorizedQueryBuilder($usuario)
            ->orderBy('finca.nombre')
            ->getQuery()
            ->getResult();

        return $this->render('farm/index.html.twig', ['fincas' => $fincas]);
    }

    #[Route('/farms/new', name: 'app_farm_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($redirect = $this->redirectWhenCannotAdminister()) {
            return $redirect;
        }
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        $organizacion = $usuario->getOrganizacion();
        if (null === $organizacion) {
            throw $this->createAccessDeniedException();
        }

        $finca = (new Finca('', ''))
            ->setOrganizacion($organizacion)
            ->setCreadoPor($usuario)
            ->setActualizadoPor($usuario);
        $form = $this->createForm(FincaType::class, $finca);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($finca);
            $entityManager->flush();
            $this->addFlash('success', 'La finca fue creada correctamente.');

            return $this->redirectToRoute('app_farm_index');
        }

        return $this->render('farm/form.html.twig', [
            'form' => $form,
            'page_title' => 'Nueva finca',
            'submit_label' => 'Crear finca',
        ]);
    }

    #[Route('/farms/{finca}/edit', name: 'app_farm_edit', methods: ['GET', 'POST'], requirements: ['finca' => '\\d+'])]
    public function edit(Finca $finca, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($redirect = $this->redirectWhenCannotAdminister()) {
            return $redirect;
        }

        $this->denyAccessUnlessGranted(FincaVoter::MANAGE, $finca);
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        $form = $this->createForm(FincaType::class, $finca);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $finca->setActualizadoPor($usuario);
            $entityManager->flush();
            $this->addFlash('success', 'La finca fue actualizada correctamente.');

            return $this->redirectToRoute('app_farm_index');
        }

        return $this->render('farm/form.html.twig', [
            'form' => $form,
            'page_title' => 'Editar finca',
            'submit_label' => 'Guardar cambios',
        ]);
    }

    #[Route('/farms/{finca}', name: 'app_farm_show', methods: ['GET'], requirements: ['finca' => '\\d+'])]
    public function show(Finca $finca): Response
    {
        $this->denyAccessUnlessGranted(FincaVoter::VIEW, $finca);

        return $this->render('farm/show.html.twig', ['finca' => $finca]);
    }

    private function redirectWhenCannotAdminister(): ?RedirectResponse
    {
        if ($this->isGranted('ROLE_ADMINISTRADOR')) {
            return null;
        }

        $this->addFlash('warning', 'Tu cuenta no tiene permiso para administrar fincas.');

        return $this->redirectToRoute('app_health');
    }
}
