<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Usuario;
use App\Entity\Vacuna;
use App\Entity\Vacunacion;
use App\Form\VacunacionType;
use App\Repository\AnimalRepository;
use App\Repository\VacunacionRepository;
use App\Security\FincaAccess;
use App\Validation\FechaFueraDeRangoException;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** RF-07: vacunaciones. Ambos roles registran, consultan y modifican; solo el Administrador elimina. */
final class VacunacionController extends AbstractController
{
    private const MENSAJE_SOLO_LECTURA = 'La finca del animal está inactiva; su historial de vacunaciones es de solo lectura.';

    #[Route('/vaccinations', name: 'app_vaccination_index', methods: ['GET'])]
    public function index(VacunacionRepository $vacunacionRepository): Response
    {
        return $this->render('vaccination/index.html.twig', [
            'vacunaciones' => $vacunacionRepository->createAuthorizedQueryBuilder($this->usuario())->setMaxResults(200)->getQuery()->getResult(),
        ]);
    }

    #[Route('/vaccinations/new', name: 'app_vaccination_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, AnimalRepository $animalRepository): Response
    {
        $usuario = $this->usuario();
        $animalSugerido = $request->query->has('animal')
            ? $animalRepository->createRegistrableQueryBuilder($usuario)
                ->andWhere('animal.id = :id')->setParameter('id', $request->query->getInt('animal'))
                ->getQuery()->getOneOrNullResult()
            : null;

        $form = $this->createForm(VacunacionType::class, ['animal' => $animalSugerido], ['usuario' => $usuario]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data) && $data['animal'] instanceof Animal && $data['vacuna'] instanceof Vacuna);

            try {
                $vacunacion = (new Vacunacion($data['animal'], $data['vacuna'], $data['fecha'], (string) $data['dosis']))
                    ->setObservaciones($this->textoOpcional($data['observaciones']));
                $entityManager->persist($vacunacion);
                $entityManager->flush();
                $this->addFlash('success', 'La vacunación fue registrada correctamente.');

                return $this->redirectToRoute('app_vaccination_history', ['animal' => $vacunacion->getAnimal()->getId()]);
            } catch (FechaFueraDeRangoException $exception) {
                $form->get('fecha')->addError(new FormError($exception->getMessage()));
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('vaccination/form.html.twig', [
            'form' => $form,
            'vacunacion' => null,
            'page_title' => 'Registrar vacunación',
            'submit_label' => 'Registrar vacunación',
        ], $this->respuesta($form));
    }

    #[Route('/animals/{animal}/vaccinations', name: 'app_vaccination_history', methods: ['GET'], requirements: ['animal' => '\\d+'])]
    public function history(Animal $animal, VacunacionRepository $vacunacionRepository, FincaAccess $fincaAccess): Response
    {
        if (!$fincaAccess->canView($this->usuario(), $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('vaccination/history.html.twig', [
            'animal' => $animal,
            'vacunaciones' => $vacunacionRepository->historial($animal),
        ]);
    }

    #[Route('/vaccinations/{vacunacion}/edit', name: 'app_vaccination_edit', methods: ['GET', 'POST'], requirements: ['vacunacion' => '\\d+'])]
    public function edit(Vacunacion $vacunacion, Request $request, EntityManagerInterface $entityManager, FincaAccess $fincaAccess): Response
    {
        $usuario = $this->usuario();
        $animal = $vacunacion->getAnimal();
        if (!$fincaAccess->canView($usuario, $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }
        if (!$animal->getFinca()->isActivo()) {
            $this->addFlash('warning', self::MENSAJE_SOLO_LECTURA);

            return $this->redirectToRoute('app_vaccination_history', ['animal' => $animal->getId()]);
        }

        $form = $this->createForm(VacunacionType::class, [
            'vacuna' => $vacunacion->getVacuna(),
            'fecha' => $vacunacion->getFecha(),
            'dosis' => $vacunacion->getDosis(),
            'observaciones' => $vacunacion->getObservaciones(),
        ], ['usuario' => $usuario, 'elegir_animal' => false, 'vacuna_actual' => $vacunacion->getVacuna()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data) && $data['vacuna'] instanceof Vacuna);

            try {
                $vacunacion->modificar($data['vacuna'], $data['fecha'], (string) $data['dosis'], $this->textoOpcional($data['observaciones']));
                $entityManager->flush();
                $this->addFlash('success', 'La vacunación fue actualizada correctamente.');

                return $this->redirectToRoute('app_vaccination_history', ['animal' => $animal->getId()]);
            } catch (FechaFueraDeRangoException $exception) {
                $form->get('fecha')->addError(new FormError($exception->getMessage()));
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('vaccination/form.html.twig', [
            'form' => $form,
            'vacunacion' => $vacunacion,
            'page_title' => 'Editar vacunación',
            'submit_label' => 'Guardar cambios',
        ], $this->respuesta($form));
    }

    #[Route('/vaccinations/{vacunacion}/delete', name: 'app_vaccination_delete', methods: ['POST'], requirements: ['vacunacion' => '\\d+'])]
    public function delete(Vacunacion $vacunacion, Request $request, EntityManagerInterface $entityManager, FincaAccess $fincaAccess): Response
    {
        $animal = $vacunacion->getAnimal();
        if (!$this->isGranted('ROLE_ADMINISTRADOR') || !$fincaAccess->canView($this->usuario(), $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        if (!$animal->getFinca()->isActivo()) {
            $this->addFlash('warning', self::MENSAJE_SOLO_LECTURA);
        } elseif ($this->isCsrfTokenValid('delete-vaccination-'.$vacunacion->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($vacunacion);
            $entityManager->flush();
            $this->addFlash('success', 'La vacunación fue eliminada correctamente.');
        } else {
            $this->addFlash('warning', 'La solicitud expiró. Intenta eliminar la vacunación de nuevo.');
        }

        return $this->redirectToRoute('app_vaccination_history', ['animal' => $animal->getId()]);
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return '' === $valor ? null : $valor;
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
