<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\ProduccionLeche;
use App\Entity\Usuario;
use App\Form\ProduccionLecheType;
use App\Repository\AnimalRepository;
use App\Repository\ProduccionLecheRepository;
use App\Security\FincaAccess;
use App\Validation\FechaFueraDeRangoException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RF-09: producción de leche. Ambos roles registran, consultan y modifican; solo el Administrador elimina.
 * El ámbito de cada ordeño lo marca la finca donde ocurrió, no la finca actual del animal.
 */
final class ProduccionLecheController extends AbstractController
{
    private const MENSAJE_SOLO_LECTURA = 'La finca del ordeño está inactiva; el registro es de solo lectura.';

    #[Route('/milk', name: 'app_milk_index', methods: ['GET'])]
    public function index(Request $request, ProduccionLecheRepository $ordenoRepository): Response
    {
        $usuario = $this->usuario();
        try {
            $dia = new DateTimeImmutable($request->query->getString('fecha', 'today'));
        } catch (Exception) {
            $dia = new DateTimeImmutable('today');
        }
        $dia = $dia->setTime(0, 0);

        return $this->render('milk/index.html.twig', [
            'dia' => $dia,
            'totalesPorFinca' => $ordenoRepository->totalesPorFinca($usuario, $dia),
            'ordenos' => $ordenoRepository->createAuthorizedQueryBuilder($usuario)->setMaxResults(200)->getQuery()->getResult(),
        ]);
    }

    #[Route('/milk/new', name: 'app_milk_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, AnimalRepository $animalRepository): Response
    {
        $usuario = $this->usuario();
        $animalSugerido = $request->query->has('animal')
            ? $animalRepository->createRegistrableQueryBuilder($usuario)
                ->andWhere('animal.id = :id')->setParameter('id', $request->query->getInt('animal'))
                ->getQuery()->getOneOrNullResult()
            : null;

        $form = $this->createForm(ProduccionLecheType::class, [
            'animal' => $animalSugerido,
            // Se propone la finca actual del animal; el usuario confirma la finca real del ordeño.
            'fincaOrdeno' => $animalSugerido?->getFinca(),
            'fechaHora' => new DateTimeImmutable(date('Y-m-d H:i')),
        ], ['usuario' => $usuario]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data) && $data['animal'] instanceof Animal && $data['fincaOrdeno'] instanceof Finca);

            try {
                $ordeno = new ProduccionLeche($data['animal'], $data['fincaOrdeno'], $data['fechaHora'], (string) $data['cantidadLitros']);
                $entityManager->persist($ordeno);
                $entityManager->flush();
                $this->addFlash('success', 'El ordeño fue registrado correctamente.');

                return $this->redirectToRoute('app_milk_history', ['animal' => $ordeno->getAnimal()->getId()]);
            } catch (FechaFueraDeRangoException $exception) {
                $form->get('fechaHora')->addError(new FormError($exception->getMessage()));
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('milk/form.html.twig', [
            'form' => $form,
            'ordeno' => null,
            'page_title' => 'Registrar ordeño',
            'submit_label' => 'Registrar ordeño',
        ], $this->respuesta($form));
    }

    #[Route('/animals/{animal}/milk', name: 'app_milk_history', methods: ['GET'], requirements: ['animal' => '\\d+'])]
    public function history(Animal $animal, ProduccionLecheRepository $ordenoRepository, FincaAccess $fincaAccess): Response
    {
        $usuario = $this->usuario();
        if (!$fincaAccess->canView($usuario, $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('milk/history.html.twig', [
            'animal' => $animal,
            'ordenos' => $ordenoRepository->historial($animal, $usuario),
            'totalesDiarios' => $ordenoRepository->totalesDiarios($animal, $usuario),
        ]);
    }

    #[Route('/milk/{ordeno}/edit', name: 'app_milk_edit', methods: ['GET', 'POST'], requirements: ['ordeno' => '\\d+'])]
    public function edit(ProduccionLeche $ordeno, Request $request, EntityManagerInterface $entityManager, FincaAccess $fincaAccess): Response
    {
        $usuario = $this->usuario();
        if (!$fincaAccess->canView($usuario, $ordeno->getFincaOrdeno())) {
            throw $this->createAccessDeniedException();
        }
        if (!$ordeno->getFincaOrdeno()->isActivo()) {
            $this->addFlash('warning', self::MENSAJE_SOLO_LECTURA);

            return $this->redirectToRoute('app_milk_history', ['animal' => $ordeno->getAnimal()->getId()]);
        }

        $form = $this->createForm(ProduccionLecheType::class, [
            'fincaOrdeno' => $ordeno->getFincaOrdeno(),
            'fechaHora' => $ordeno->getFechaHora(),
            'cantidadLitros' => $ordeno->getCantidadLitros(),
        ], ['usuario' => $usuario, 'elegir_animal' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data) && $data['fincaOrdeno'] instanceof Finca);

            try {
                $ordeno->modificar($data['fincaOrdeno'], $data['fechaHora'], (string) $data['cantidadLitros']);
                $entityManager->flush();
                $this->addFlash('success', 'El ordeño fue actualizado correctamente.');

                return $this->redirectToRoute('app_milk_history', ['animal' => $ordeno->getAnimal()->getId()]);
            } catch (FechaFueraDeRangoException $exception) {
                $form->get('fechaHora')->addError(new FormError($exception->getMessage()));
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('milk/form.html.twig', [
            'form' => $form,
            'ordeno' => $ordeno,
            'page_title' => 'Editar ordeño',
            'submit_label' => 'Guardar cambios',
        ], $this->respuesta($form));
    }

    #[Route('/milk/{ordeno}/delete', name: 'app_milk_delete', methods: ['POST'], requirements: ['ordeno' => '\\d+'])]
    public function delete(ProduccionLeche $ordeno, Request $request, EntityManagerInterface $entityManager, FincaAccess $fincaAccess): Response
    {
        $animal = $ordeno->getAnimal();
        if (!$this->isGranted('ROLE_ADMINISTRADOR') || !$fincaAccess->canView($this->usuario(), $ordeno->getFincaOrdeno())) {
            throw $this->createAccessDeniedException();
        }

        if (!$ordeno->getFincaOrdeno()->isActivo()) {
            $this->addFlash('warning', self::MENSAJE_SOLO_LECTURA);
        } elseif ($this->isCsrfTokenValid('delete-milk-'.$ordeno->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($ordeno);
            $entityManager->flush();
            $this->addFlash('success', 'El ordeño fue eliminado correctamente.');
        } else {
            $this->addFlash('warning', 'La solicitud expiró. Intenta eliminar el ordeño de nuevo.');
        }

        return $this->redirectToRoute('app_milk_history', ['animal' => $animal->getId()]);
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
