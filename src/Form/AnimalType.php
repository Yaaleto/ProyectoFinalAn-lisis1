<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Raza;
use App\Entity\Usuario;
use App\Repository\FincaRepository;
use App\Repository\RazaRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use SortDirection;

/** @extends AbstractType<array<string, mixed>> */
final class AnimalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('arete', TextType::class, [
                'label' => 'Arete',
                'constraints' => [new Assert\NotBlank(message: 'El arete es obligatorio.'), new Assert\Length(max: 50, maxMessage: 'El arete admite hasta {{ limit }} caracteres.')],
                'help' => 'Debe ser único dentro de la organización.',
                'attr' => ['autocomplete' => 'off'],
            ])
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'required' => false,
                'constraints' => [new Assert\Length(max: 120, maxMessage: 'El nombre admite hasta {{ limit }} caracteres.')],
            ])
            ->add('finca', EntityType::class, [
                'label' => 'Finca',
                'class' => Finca::class,
                'choice_label' => static fn (Finca $finca): string => sprintf('%s · %s', $finca->getNombre(), $finca->getCodigo()),
                // Solo fincas activas; un animal existente conserva su finca aunque se haya desactivado.
                'query_builder' => static function (FincaRepository $repository) use ($options) {
                    $queryBuilder = $repository->createAuthorizedQueryBuilder($options['user'])
                        ->setParameter('activa', true)
                        ->orderBy('finca.nombre', SortDirection::Ascending);
                    if (null === $options['animal']) {
                        return $queryBuilder->andWhere('finca.activo = :activa');
                    }

                    return $queryBuilder
                        ->andWhere('(finca.activo = :activa OR finca = :fincaActual)')
                        ->setParameter('fincaActual', $options['animal']->getFinca());
                },
                'placeholder' => 'Selecciona una finca',
                'constraints' => [new Assert\NotNull(message: 'Selecciona una finca.')],
            ])
            ->add('raza', EntityType::class, [
                'label' => 'Raza',
                'class' => Raza::class,
                'choice_label' => 'nombre',
                // Solo razas activas; un animal existente conserva su raza aunque se haya desactivado (CA-03-C).
                'query_builder' => static function (RazaRepository $repository) use ($options) {
                    $queryBuilder = $repository->createActiveOrganizationQueryBuilder($options['organization'])
                        ->orderBy('raza.nombre', SortDirection::Ascending);
                    $razaActual = $options['animal']?->getRaza();
                    if (null !== $razaActual) {
                        $queryBuilder->orWhere('raza = :razaActual')->setParameter('razaActual', $razaActual);
                    }

                    return $queryBuilder;
                },
                'placeholder' => 'Sin raza',
                'required' => false,
            ])
            ->add('sexo', ChoiceType::class, [
                'label' => 'Sexo',
                'choices' => ['Hembra' => Animal::SEXO_HEMBRA, 'Macho' => Animal::SEXO_MACHO],
                'constraints' => [new Assert\NotBlank(message: 'Selecciona el sexo.')],
            ]);

        // El ingreso de un animal externo no pide nacimiento ni peso al nacer; se agregan al editarlo.
        if (Animal::ORIGEN_EXTERNO !== $options['intake_origin']) {
            $builder
                ->add('fechaNacimiento', DateType::class, [
                    'label' => 'Fecha de nacimiento',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'required' => Animal::ORIGEN_NACIDO_EN_FINCA === $options['intake_origin'],
                    'constraints' => array_filter([
                        Animal::ORIGEN_NACIDO_EN_FINCA === $options['intake_origin'] ? new Assert\NotBlank(message: 'Indica la fecha de nacimiento.') : null,
                        new Assert\LessThanOrEqual('today', message: 'La fecha de nacimiento no puede estar en el futuro.'),
                    ]),
                ])
                ->add('pesoNacimientoKg', NumberType::class, [
                    'label' => 'Peso al nacimiento (kg)',
                    'input' => 'string',
                    'scale' => 2,
                    'required' => false,
                    'constraints' => [
                        new Assert\Positive(message: 'El peso al nacimiento debe ser mayor que cero.'),
                        new Assert\LessThanOrEqual(value: 999.99, message: 'El peso al nacimiento admite hasta 999.99 kg.'),
                    ],
                ]);
        }

        // RN-19: en un nacido en finca la fecha de ingreso es la de nacimiento, por eso no se pide.
        if (Animal::ORIGEN_EXTERNO === ($options['intake_origin'] ?? $options['animal']?->getOrigen())) {
            $builder->add('fechaIngreso', DateType::class, [
                'label' => 'Fecha de ingreso',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Día en que el animal llegó a la finca. No se aceptan registros anteriores a esta fecha.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Indica la fecha de ingreso.'),
                    new Assert\LessThanOrEqual('today', message: 'La fecha de ingreso no puede estar en el futuro.'),
                ],
            ]);
        }

        $builder
            ->add('productoraLeche', CheckboxType::class, [
                'label' => 'Productora de leche',
                'required' => false,
                'help' => 'Solo puede activarse para hembras.',
            ]);

        if (null === $options['intake_origin']) {
            $builder->add('origen', ChoiceType::class, [
                'label' => 'Origen',
                'choices' => [
                    'Nacido en finca' => Animal::ORIGEN_NACIDO_EN_FINCA,
                    'Externo' => Animal::ORIGEN_EXTERNO,
                ],
                'constraints' => [new Assert\NotBlank(message: 'Selecciona el origen.')],
            ]);
        }

        if (Animal::ORIGEN_NACIDO_EN_FINCA !== $options['intake_origin']) {
            $builder->add('procedencia', TextType::class, [
                'label' => 'Procedencia',
                'required' => false,
                'help' => 'Opcional. Indica la finca o lugar de procedencia (hasta 200 caracteres).',
                'constraints' => [new Assert\Length(max: 200, maxMessage: 'La procedencia admite hasta {{ limit }} caracteres.')],
            ]);
        }

        foreach (['padre' => Animal::SEXO_MACHO, 'madre' => Animal::SEXO_HEMBRA] as $campo => $sexo) {
            $animal = $options['animal'];
            $progenitorActual = $animal instanceof Animal
                ? ('padre' === $campo ? $animal->getPadre() : $animal->getMadre())
                : null;
            $builder->add($campo, EntityType::class, [
                'label' => ucfirst($campo),
                'class' => Animal::class,
                'choice_label' => static fn (Animal $progenitor): string => sprintf(
                    '%s%s · %s',
                    $progenitor->getArete() ?: $progenitor->getNombre() ?: 'Animal n.º '.$progenitor->getId(),
                    $progenitor->getArete() && $progenitor->getNombre() ? ' — '.$progenitor->getNombre() : '',
                    $progenitor->getFinca()->getNombre(),
                ),
                'query_builder' => static function (EntityRepository $repository) use ($options, $sexo, $animal, $progenitorActual) {
                    $queryBuilder = $repository->createQueryBuilder('progenitor')
                        ->andWhere('progenitor.organizacion = :organizacion')
                        ->andWhere('progenitor.sexo = :sexo')
                        ->setParameter('organizacion', $options['organization'])
                        ->setParameter('sexo', $sexo)
                        ->orderBy('progenitor.arete')
                        ->addOrderBy('progenitor.nombre');

                    if ($animal instanceof Animal && null !== $animal->getId()) {
                        $queryBuilder->andWhere('progenitor.id != :animalId')->setParameter('animalId', $animal->getId());
                    }

                    $usuario = $options['user'];
                    if (Usuario::ROL_ENCARGADO === $usuario->getRol()) {
                        $finca = $usuario->getFincaActiva();
                        if (null === $finca) {
                            $queryBuilder->andWhere('1 = 0');
                        } elseif (null !== $progenitorActual) {
                            $queryBuilder->andWhere('(progenitor.finca = :finca OR progenitor = :progenitorActual)')
                                ->setParameter('finca', $finca)
                                ->setParameter('progenitorActual', $progenitorActual);
                        } else {
                            $queryBuilder->andWhere('progenitor.finca = :finca')->setParameter('finca', $finca);
                        }
                    } elseif (Usuario::ROL_ADMINISTRADOR !== $usuario->getRol()) {
                        $queryBuilder->andWhere('1 = 0');
                    }

                    return $queryBuilder;
                },
                'placeholder' => 'Sin '.$campo.' registrado',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'intake_origin' => null, 'animal' => null]);
        $resolver->setRequired(['organization', 'user']);
        $resolver->setAllowedTypes('organization', OrganizacionGanadera::class);
        $resolver->setAllowedTypes('user', Usuario::class);
        $resolver->setAllowedTypes('animal', ['null', Animal::class]);
        $resolver->setAllowedValues('intake_origin', [null, Animal::ORIGEN_NACIDO_EN_FINCA, Animal::ORIGEN_EXTERNO]);
    }
}
