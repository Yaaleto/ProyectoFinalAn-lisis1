<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\Raza;
use App\Entity\Usuario;
use App\Form\AnimalType;
use App\Security\FincaAccess;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
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
            ->orderBy('animal.arete')
            ->addOrderBy('animal.nombre');

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

    #[Route('/animals/new', name: 'app_animal_new', defaults: ['intakeOrigin' => Animal::ORIGEN_EXTERNO], methods: ['GET', 'POST'])]
    #[Route('/animals/new/birth', name: 'app_animal_birth', defaults: ['intakeOrigin' => Animal::ORIGEN_NACIDO_EN_FINCA], methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator, string $intakeOrigin): Response
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
            'intake_origin' => $intakeOrigin,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data));
            \assert($data['finca'] instanceof Finca);
            \assert(null === $data['raza'] || $data['raza'] instanceof Raza);

            try {
                $animal = new Animal(
                    $organizacion,
                    $data['finca'],
                    $this->generateInternalCode(),
                    (string) $data['sexo'],
                    $intakeOrigin,
                );
                $this->applyFormData($animal, $data, $intakeOrigin);
                $animal->setCreadoPor($usuario)->setActualizadoPor($usuario);

                if (!$this->addValidationErrors($form, $animal, $validator)) {
                    $entityManager->persist($animal);
                    $entityManager->flush();
                    $this->addFlash('success', 'El animal fue registrado correctamente.');

                    return $this->redirectToRoute('app_animal_index');
                }
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('animal/form.html.twig', [
            'form' => $form,
            'animal' => null,
            'intake_origin' => $intakeOrigin,
            'page_title' => 'Registrar animal',
            'submit_label' => Animal::ORIGEN_NACIDO_EN_FINCA === $intakeOrigin ? 'Registrar nacimiento' : 'Registrar ingreso',
        ]);
    }

    #[Route('/animals/{animal}', name: 'app_animal_show', methods: ['GET'], requirements: ['animal' => '\\d+'])]
    public function show(Animal $animal, FincaAccess $fincaAccess): Response
    {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        if (!$fincaAccess->canView($usuario, $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('animal/show.html.twig', ['animal' => $animal]);
    }

    #[Route('/animals/{animal}/edit', name: 'app_animal_edit', methods: ['GET', 'POST'], requirements: ['animal' => '\\d+'])]
    public function edit(
        Animal $animal,
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        FincaAccess $fincaAccess,
    ): Response {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        if (!$fincaAccess->canView($usuario, $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AnimalType::class, $this->formData($animal), [
            'organization' => $animal->getOrganizacion(),
            'user' => $usuario,
            'animal' => $animal,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data));

            try {
                $this->applyFormData($animal, $data);
                $animal->setActualizadoPor($usuario);

                if (!$this->addValidationErrors($form, $animal, $validator)) {
                    $entityManager->flush();
                    $this->addFlash('success', 'El animal fue actualizado correctamente.');

                    return $this->redirectToRoute('app_animal_index');
                }
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('animal/form.html.twig', [
            'form' => $form,
            'animal' => $animal,
            'intake_origin' => null,
            'page_title' => 'Editar animal',
            'submit_label' => 'Guardar cambios',
        ]);
    }

    private function generateInternalCode(): string
    {
        return 'AN-'.strtoupper(bin2hex(random_bytes(6)));
    }

    /** @param array<string, mixed> $data */
    private function applyFormData(Animal $animal, array $data, ?string $intakeOrigin = null): void
    {
        \assert($data['finca'] instanceof Finca);
        \assert(null === $data['raza'] || $data['raza'] instanceof Raza);
        \assert(null === $data['padre'] || $data['padre'] instanceof Animal);
        \assert(null === $data['madre'] || $data['madre'] instanceof Animal);

        $origin = $intakeOrigin ?? (string) $data['origen'];

        $animal
            ->setPadre($data['padre'])
            ->setMadre($data['madre'])
            ->setProductoraLeche(false)
            ->setFinca($data['finca'])
            ->setSexo((string) $data['sexo'])
            ->setOrigen($origin)
            ->setArete($this->optionalString($data['arete']))
            ->setNombre($this->optionalString($data['nombre']))
            ->setRaza($data['raza'])
            ->setFechaNacimiento($data['fechaNacimiento'])
            ->setPesoNacimientoKg($data['pesoNacimientoKg'])
            ->setProcedencia(Animal::ORIGEN_EXTERNO === $origin ? $this->optionalString($data['procedencia']) : null)
            ->setProductoraLeche((bool) $data['productoraLeche']);
    }

    /** @return array<string, mixed> */
    private function formData(Animal $animal): array
    {
        return [
            'arete' => $animal->getArete(),
            'nombre' => $animal->getNombre(),
            'finca' => $animal->getFinca(),
            'raza' => $animal->getRaza(),
            'padre' => $animal->getPadre(),
            'madre' => $animal->getMadre(),
            'sexo' => $animal->getSexo(),
            'fechaNacimiento' => $animal->getFechaNacimiento(),
            'pesoNacimientoKg' => $animal->getPesoNacimientoKg(),
            'origen' => $animal->getOrigen(),
            'procedencia' => $animal->getProcedencia(),
            'productoraLeche' => $animal->isProductoraLeche(),
        ];
    }

    private function addValidationErrors(FormInterface $form, Animal $animal, ValidatorInterface $validator): bool
    {
        $violations = $validator->validate($animal);
        foreach ($violations as $violation) {
            $field = $form->has($violation->getPropertyPath())
                ? $form->get($violation->getPropertyPath())
                : $form;
            $field->addError(new FormError((string) $violation->getMessage()));
        }

        return count($violations) > 0;
    }

    private function optionalString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
