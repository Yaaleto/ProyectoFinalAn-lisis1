<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\Raza;
use App\Entity\Usuario;
use App\Form\AnimalType;
use App\Form\SalidaAnimalType;
use App\Repository\MontaReproductivaRepository;
use App\Repository\PesajeRepository;
use App\Repository\ProduccionLecheRepository;
use App\Repository\VacunacionRepository;
use App\Security\FincaAccess;
use App\Service\AnimalHistorial;
use App\Service\Genealogia;
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
    public function index(Request $request, EntityManagerInterface $entityManager): Response
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

        // Búsqueda por arete o nombre dentro del ámbito ya aplicado (RF-10, CA-10-A).
        $busqueda = trim($request->query->getString('q'));
        if ('' !== $busqueda) {
            $queryBuilder
                ->andWhere('(animal.arete LIKE :busqueda OR animal.nombre LIKE :busqueda)')
                ->setParameter('busqueda', '%'.addcslashes($busqueda, '%_\\').'%');
        }

        return $this->render('animal/index.html.twig', [
            'animales' => $queryBuilder->getQuery()->getResult(),
            'busqueda' => $busqueda,
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
    /** Ficha integral (RF-10): datos, genealogía y los cuatro historiales dentro del ámbito del usuario. Solo lectura. */
    public function show(
        Animal $animal,
        FincaAccess $fincaAccess,
        Genealogia $genealogia,
        PesajeRepository $pesajeRepository,
        VacunacionRepository $vacunacionRepository,
        MontaReproductivaRepository $montaRepository,
        ProduccionLecheRepository $ordenoRepository,
    ): Response {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        if (!$fincaAccess->canView($usuario, $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('animal/show.html.twig', [
            'animal' => $animal,
            'genealogia' => $genealogia->ascendencia($animal, $usuario),
            'pesajes' => $pesajeRepository->historialConVariacion($animal),
            'vacunaciones' => $vacunacionRepository->historial($animal),
            'montas' => $montaRepository->historial($animal, $usuario),
            'ordenos' => $ordenoRepository->historial($animal, $usuario),
            'totalesLeche' => $ordenoRepository->totalesDiarios($animal, $usuario),
        ]);
    }

    #[Route('/animals/{animal}/edit', name: 'app_animal_edit', methods: ['GET', 'POST'], requirements: ['animal' => '\\d+'])]
    public function edit(
        Animal $animal,
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        FincaAccess $fincaAccess,
        AnimalHistorial $historial,
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
                // Se consulta antes de aplicar el formulario, con el sexo que el animal tiene guardado.
                $sexoConRegistros = $historial->tieneRegistrosQueDependenDelSexo($animal) ? $animal->getSexo() : null;
                $this->applyFormData($animal, $data);

                if (null !== $sexoConRegistros && $sexoConRegistros !== $animal->getSexo()) {
                    $form->get('sexo')->addError(new FormError(Animal::SEXO_HEMBRA === $sexoConRegistros
                        ? 'No puedes cambiar el sexo de una hembra con montas o producción de leche registradas.'
                        : 'No puedes cambiar el sexo de un macho con montas registradas.'));
                }

                // RN-11 también al editar: el nacimiento no puede quedar después de registros ya guardados.
                $primerRegistro = $historial->primeraFechaOperativa($animal);
                $nacimiento = $animal->getFechaNacimiento();
                if (null !== $primerRegistro && null !== $nacimiento && $nacimiento->format('Y-m-d') > $primerRegistro->format('Y-m-d')) {
                    $form->get('fechaNacimiento')->addError(new FormError(sprintf(
                        'La fecha de nacimiento no puede ser posterior al primer registro operativo del animal (%s).',
                        $primerRegistro->format('d/m/Y'),
                    )));
                }

                if ($form->isValid() && !$this->addValidationErrors($form, $animal, $validator)) {
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

    /** Cambio de estado con fecha de salida (CA-04-C); disponible para ambos roles dentro de su ámbito. */
    #[Route('/animals/{animal}/status', name: 'app_animal_status', methods: ['GET', 'POST'], requirements: ['animal' => '\\d+'])]
    public function status(
        Animal $animal,
        Request $request,
        EntityManagerInterface $entityManager,
        FincaAccess $fincaAccess,
        AnimalHistorial $historial,
    ): Response {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        if (!$fincaAccess->canView($usuario, $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(SalidaAnimalType::class, [
            'estado' => $animal->getEstado(),
            'fechaSalida' => $animal->getFechaSalida(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            \assert(is_array($data));

            try {
                if (Animal::ESTADO_ACTIVO === $data['estado']) {
                    $animal->reactivar();
                } else {
                    $animal->registrarSalida((string) $data['estado'], $data['fechaSalida'], $historial->ultimaFechaOperativa($animal));
                }
                $entityManager->flush();
                $this->addFlash('success', 'El estado del animal fue actualizado correctamente.');

                return $this->redirectToRoute('app_animal_show', ['animal' => $animal->getId()]);
            } catch (DomainException $exception) {
                $form->get('fechaSalida')->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('animal/status.html.twig', [
            'form' => $form,
            'animal' => $animal,
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Eliminación física solo por el Administrador y solo sin registros operativos ni descendientes (CA-04-D, CA-04-E). */
    #[Route('/animals/{animal}/delete', name: 'app_animal_delete', methods: ['POST'], requirements: ['animal' => '\\d+'])]
    public function delete(
        Animal $animal,
        Request $request,
        EntityManagerInterface $entityManager,
        FincaAccess $fincaAccess,
        AnimalHistorial $historial,
    ): Response {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        if (!$this->isGranted('ROLE_ADMINISTRADOR') || !$fincaAccess->canView($usuario, $animal->getFinca())) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('delete-animal-'.$animal->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('warning', 'La solicitud expiró. Intenta eliminar el animal de nuevo.');

            return $this->redirectToRoute('app_animal_show', ['animal' => $animal->getId()]);
        }

        $motivo = match (true) {
            $historial->tieneRegistrosOperativos($animal) => 'tiene registros operativos',
            $historial->tieneDescendientes($animal) => 'está registrado como progenitor de otros animales',
            default => null,
        };
        if (null !== $motivo) {
            $this->addFlash('warning', sprintf('No se puede eliminar el animal porque %s. Puedes registrar su salida en su lugar.', $motivo));

            return $this->redirectToRoute('app_animal_show', ['animal' => $animal->getId()]);
        }

        $entityManager->remove($animal);
        $entityManager->flush();
        $this->addFlash('success', 'El animal fue eliminado correctamente.');

        return $this->redirectToRoute('app_animal_index');
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
