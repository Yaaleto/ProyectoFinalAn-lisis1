<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\MontaReproductiva;
use App\Entity\Usuario;
use App\Form\MontaReproductivaType;
use App\Repository\AnimalRepository;
use App\Repository\MontaReproductivaRepository;
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

/** RF-08: montas. Ambos roles registran, consultan y modifican; solo el Administrador elimina. */
final class MontaReproductivaController extends AbstractController
{
    private const MENSAJE_SOLO_LECTURA = 'La finca de la hembra está inactiva; sus montas son de solo lectura.';

    #[Route('/matings', name: 'app_mating_index', methods: ['GET'])]
    public function index(MontaReproductivaRepository $montaRepository): Response
    {
        return $this->render('mating/index.html.twig', [
            'montas' => $montaRepository->createAuthorizedQueryBuilder($this->usuario())->setMaxResults(200)->getQuery()->getResult(),
        ]);
    }

    #[Route('/matings/new', name: 'app_mating_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, AnimalRepository $animalRepository): Response
    {
        $usuario = $this->usuario();
        $hembraSugerida = $request->query->has('animal')
            ? $animalRepository->createRegistrableQueryBuilder($usuario)
                ->andWhere('animal.id = :id')->andWhere('animal.sexo = :sexo')
                ->setParameter('id', $request->query->getInt('animal'))->setParameter('sexo', Animal::SEXO_HEMBRA)
                ->getQuery()->getOneOrNullResult()
            : null;

        $form = $this->createForm(MontaReproductivaType::class, ['hembra' => $hembraSugerida], ['usuario' => $usuario]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data) && $data['hembra'] instanceof Animal);

            try {
                $monta = (new MontaReproductiva($data['hembra'], $data['macho'], $data['fecha']))
                    ->setObservacion($this->textoOpcional($data['observacion']));
                $entityManager->persist($monta);
                $entityManager->flush();
                $this->addFlash('success', 'La monta fue registrada correctamente.');

                return $this->redirectToRoute('app_mating_history', ['animal' => $monta->getHembra()->getId()]);
            } catch (FechaFueraDeRangoException $exception) {
                $form->get('fecha')->addError(new FormError($exception->getMessage()));
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('mating/form.html.twig', [
            'form' => $form,
            'monta' => null,
            'page_title' => 'Registrar monta',
            'submit_label' => 'Registrar monta',
        ], $this->respuesta($form));
    }

    #[Route('/animals/{animal}/matings', name: 'app_mating_history', methods: ['GET'], requirements: ['animal' => '\\d+'])]
    public function history(Animal $animal, MontaReproductivaRepository $montaRepository, FincaAccess $fincaAccess): Response
    {
        $usuario = $this->usuario();
        if (!$fincaAccess->canView($usuario, $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('mating/history.html.twig', [
            'animal' => $animal,
            'montas' => $montaRepository->historial($animal, $usuario),
        ]);
    }

    #[Route('/matings/{monta}/edit', name: 'app_mating_edit', methods: ['GET', 'POST'], requirements: ['monta' => '\\d+'])]
    public function edit(MontaReproductiva $monta, Request $request, EntityManagerInterface $entityManager, FincaAccess $fincaAccess): Response
    {
        $usuario = $this->usuario();
        $hembra = $monta->getHembra();
        if (!$fincaAccess->canView($usuario, $hembra->getFinca())) {
            throw $this->createAccessDeniedException();
        }
        if (!$hembra->getFinca()->isActivo()) {
            $this->addFlash('warning', self::MENSAJE_SOLO_LECTURA);

            return $this->redirectToRoute('app_mating_history', ['animal' => $hembra->getId()]);
        }

        $form = $this->createForm(MontaReproductivaType::class, [
            'macho' => $monta->getMacho(),
            'fecha' => $monta->getFecha(),
            'observacion' => $monta->getObservacion(),
        ], ['usuario' => $usuario, 'elegir_hembra' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data));

            try {
                $monta->modificar($data['macho'], $data['fecha'], $this->textoOpcional($data['observacion']));
                $entityManager->flush();
                $this->addFlash('success', 'La monta fue actualizada correctamente.');

                return $this->redirectToRoute('app_mating_history', ['animal' => $hembra->getId()]);
            } catch (FechaFueraDeRangoException $exception) {
                $form->get('fecha')->addError(new FormError($exception->getMessage()));
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('mating/form.html.twig', [
            'form' => $form,
            'monta' => $monta,
            'page_title' => 'Editar monta',
            'submit_label' => 'Guardar cambios',
        ], $this->respuesta($form));
    }

    #[Route('/matings/{monta}/delete', name: 'app_mating_delete', methods: ['POST'], requirements: ['monta' => '\\d+'])]
    public function delete(MontaReproductiva $monta, Request $request, EntityManagerInterface $entityManager, FincaAccess $fincaAccess): Response
    {
        $hembra = $monta->getHembra();
        if (!$this->isGranted('ROLE_ADMINISTRADOR') || !$fincaAccess->canView($this->usuario(), $hembra->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        if (!$hembra->getFinca()->isActivo()) {
            $this->addFlash('warning', self::MENSAJE_SOLO_LECTURA);
        } elseif ($this->isCsrfTokenValid('delete-mating-'.$monta->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($monta);
            $entityManager->flush();
            $this->addFlash('success', 'La monta fue eliminada correctamente.');
        } else {
            $this->addFlash('warning', 'La solicitud expiró. Intenta eliminar la monta de nuevo.');
        }

        return $this->redirectToRoute('app_mating_history', ['animal' => $hembra->getId()]);
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
