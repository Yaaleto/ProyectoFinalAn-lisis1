<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Pesaje;
use App\Entity\Usuario;
use App\Form\PesajeType;
use App\Repository\AnimalRepository;
use App\Repository\PesajeRepository;
use App\Security\FincaAccess;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** RF-06: pesajes. Ambos roles registran, consultan y modifican; solo el Administrador elimina. */
final class PesajeController extends AbstractController
{
    private const MENSAJE_DUPLICADO = 'Ya existe un pesaje de este animal en esa fecha. Modifica el registro existente.';

    #[Route('/weights', name: 'app_weight_index', methods: ['GET'])]
    public function index(PesajeRepository $pesajeRepository): Response
    {
        return $this->render('weight/index.html.twig', [
            'pesajes' => $pesajeRepository->createAuthorizedQueryBuilder($this->usuario())->setMaxResults(200)->getQuery()->getResult(),
        ]);
    }

    #[Route('/weights/new', name: 'app_weight_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        AnimalRepository $animalRepository,
    ): Response {
        $usuario = $this->usuario();
        $animalSugerido = $request->query->has('animal')
            ? $animalRepository->createRegistrableQueryBuilder($usuario)
                ->andWhere('animal.id = :id')->setParameter('id', $request->query->getInt('animal'))
                ->getQuery()->getOneOrNullResult()
            : null;

        $form = $this->createForm(PesajeType::class, ['animal' => $animalSugerido], ['usuario' => $usuario]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data) && $data['animal'] instanceof Animal);

            try {
                $pesaje = new Pesaje($data['animal'], $data['fecha'], (string) $data['pesoKg']);
                if ($this->guardar($form, $pesaje, $entityManager, $validator, nuevo: true)) {
                    $this->addFlash('success', 'El pesaje fue registrado correctamente.');

                    return $this->redirectToRoute('app_weight_history', ['animal' => $pesaje->getAnimal()->getId()]);
                }
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('weight/form.html.twig', [
            'form' => $form,
            'pesaje' => null,
            'page_title' => 'Registrar pesaje',
            'submit_label' => 'Registrar pesaje',
        ], $this->respuesta($form));
    }

    #[Route('/animals/{animal}/weights', name: 'app_weight_history', methods: ['GET'], requirements: ['animal' => '\\d+'])]
    public function history(Animal $animal, PesajeRepository $pesajeRepository, FincaAccess $fincaAccess): Response
    {
        if (!$fincaAccess->canView($this->usuario(), $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('weight/history.html.twig', [
            'animal' => $animal,
            'historial' => $pesajeRepository->historialConVariacion($animal),
        ]);
    }

    #[Route('/weights/{pesaje}/edit', name: 'app_weight_edit', methods: ['GET', 'POST'], requirements: ['pesaje' => '\\d+'])]
    public function edit(
        Pesaje $pesaje,
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        FincaAccess $fincaAccess,
    ): Response {
        $usuario = $this->usuario();
        if (!$fincaAccess->canView($usuario, $pesaje->getAnimal()->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(PesajeType::class, [
            'fecha' => $pesaje->getFecha(),
            'pesoKg' => $pesaje->getPesoKg(),
        ], ['usuario' => $usuario, 'elegir_animal' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data));

            try {
                $pesaje->modificar($data['fecha'], (string) $data['pesoKg']);
                if ($this->guardar($form, $pesaje, $entityManager, $validator, nuevo: false)) {
                    $this->addFlash('success', 'El pesaje fue actualizado correctamente.');

                    return $this->redirectToRoute('app_weight_history', ['animal' => $pesaje->getAnimal()->getId()]);
                }
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('weight/form.html.twig', [
            'form' => $form,
            'pesaje' => $pesaje,
            'page_title' => 'Editar pesaje',
            'submit_label' => 'Guardar cambios',
        ], $this->respuesta($form));
    }

    #[Route('/weights/{pesaje}/delete', name: 'app_weight_delete', methods: ['POST'], requirements: ['pesaje' => '\\d+'])]
    public function delete(Pesaje $pesaje, Request $request, EntityManagerInterface $entityManager, FincaAccess $fincaAccess): Response
    {
        $animal = $pesaje->getAnimal();
        if (!$this->isGranted('ROLE_ADMINISTRADOR') || !$fincaAccess->canView($this->usuario(), $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete-weight-'.$pesaje->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($pesaje);
            $entityManager->flush();
            $this->addFlash('success', 'El pesaje fue eliminado correctamente.');
        } else {
            $this->addFlash('warning', 'La solicitud expiró. Intenta eliminar el pesaje de nuevo.');
        }

        return $this->redirectToRoute('app_weight_history', ['animal' => $animal->getId()]);
    }

    private function guardar(FormInterface $form, Pesaje $pesaje, EntityManagerInterface $entityManager, ValidatorInterface $validator, bool $nuevo): bool
    {
        foreach ($validator->validate($pesaje) as $violation) {
            $campo = $form->has($violation->getPropertyPath()) ? $form->get($violation->getPropertyPath()) : $form;
            $campo->addError(new FormError((string) $violation->getMessage()));
        }
        if (!$form->isValid()) {
            return false;
        }

        try {
            if ($nuevo) {
                $entityManager->persist($pesaje);
            }
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Respaldo ante una carrera entre dos capturas simultáneas del mismo pesaje (RN-07).
            $form->get('fecha')->addError(new FormError(self::MENSAJE_DUPLICADO));

            return false;
        }

        return true;
    }

    private function respuesta(FormInterface $form): Response
    {
        return new Response(null, $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    private function usuario(): Usuario
    {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        return $usuario;
    }
}
