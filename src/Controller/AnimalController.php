<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\Raza;
use App\Entity\Usuario;
use App\Form\AnimalType;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AnimalController extends AbstractController
{
    #[Route('/animals', name: 'app_animal_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        $organizacion = $usuario->getOrganizacion();
        if (null === $organizacion) {
            throw $this->createAccessDeniedException();
        }

        $queryBuilder = $entityManager->getRepository(Animal::class)
            ->createQueryBuilder('animal')
            ->addSelect('finca', 'raza')
            ->join('animal.finca', 'finca')
            ->leftJoin('animal.raza', 'raza')
            ->andWhere('animal.organizacion = :organizacion')
            ->setParameter('organizacion', $organizacion)
            ->orderBy('animal.codigoInterno');

        if (Usuario::ROL_ENCARGADO === $usuario->getRol()) {
            $fincaActiva = $usuario->getFincaActiva();
            if (null === $fincaActiva) {
                $this->addFlash('warning', 'Tu cuenta todavía no tiene una finca asignada.');

                return $this->redirectToRoute('app_health');
            }

            $queryBuilder
                ->andWhere('animal.finca = :fincaActiva')
                ->setParameter('fincaActiva', $fincaActiva);
        }

        return $this->render('animal/index.html.twig', [
            'animales' => $queryBuilder->getQuery()->getResult(),
        ]);
    }

    #[Route('/animals/new', name: 'app_animal_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        $organizacion = $usuario->getOrganizacion();
        if (null === $organizacion) {
            throw $this->createAccessDeniedException();
        }
        if (Usuario::ROL_ENCARGADO === $usuario->getRol() && null === $usuario->getFincaActiva()) {
            $this->addFlash('warning', 'Tu cuenta todavía no tiene una finca asignada.');

            return $this->redirectToRoute('app_health');
        }

        $form = $this->createForm(AnimalType::class, null, [
            'organization' => $organizacion,
            'user' => $usuario,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data));
            \assert($data['finca'] instanceof Finca);
            \assert(null === $data['raza'] || $data['raza'] instanceof Raza);

            try {
                $animal = (new Animal(
                    $organizacion,
                    $data['finca'],
                    (string) $data['codigoInterno'],
                    (string) $data['sexo'],
                    (string) $data['origen'],
                ))
                    ->setArete($this->optionalString($data['arete']))
                    ->setNombre($this->optionalString($data['nombre']))
                    ->setRaza($data['raza'])
                    ->setFechaNacimiento($data['fechaNacimiento'])
                    ->setPesoNacimientoKg($data['pesoNacimientoKg'])
                    ->setProcedencia($this->optionalString($data['procedencia']))
                    ->setProductoraLeche((bool) $data['productoraLeche'])
                    ->setCreadoPor($usuario)
                    ->setActualizadoPor($usuario);

                $violations = $validator->validate($animal);
                foreach ($violations as $violation) {
                    $field = $form->has($violation->getPropertyPath())
                        ? $form->get($violation->getPropertyPath())
                        : $form;
                    $field->addError(new FormError((string) $violation->getMessage()));
                }

                if (0 === count($violations)) {
                    $entityManager->persist($animal);
                    $entityManager->flush();
                    $this->addFlash('success', 'El animal fue registrado correctamente.');

                    return $this->redirectToRoute('app_animal_index');
                }
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('animal/form.html.twig', ['form' => $form]);
    }

    private function optionalString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
