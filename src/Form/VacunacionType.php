<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Animal;
use App\Entity\Usuario;
use App\Entity\Vacuna;
use App\Repository\AnimalRepository;
use App\Repository\VacunaRepository;
use SortDirection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Alta y edición de vacunaciones (RF-07). Solo se ofrecen vacunas activas; al editar se conserva
 * la vacuna aplicada aunque esté inactiva. Ningún campo recibe la organización.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class VacunacionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $organizacion = $options['usuario']->getOrganizacion();
        $vacunaActual = $options['vacuna_actual'];

        if ($options['elegir_animal']) {
            $builder->add('animal', EntityType::class, [
                'label' => 'Animal',
                'class' => Animal::class,
                'choice_label' => static fn (Animal $animal): string => sprintf('%s · %s', $animal->getArete(), $animal->getNombre() ?? $animal->getFinca()->getNombre()),
                'query_builder' => static fn (AnimalRepository $repository) => $repository->createRegistrableQueryBuilder($options['usuario']),
                'placeholder' => 'Selecciona un animal',
                'constraints' => [new Assert\NotNull(message: 'Selecciona un animal.')],
            ]);
        }

        $builder
            ->add('vacuna', EntityType::class, [
                'label' => 'Vacuna',
                'class' => Vacuna::class,
                'choice_label' => static fn (Vacuna $vacuna): string => $vacuna->isActiva() ? $vacuna->getNombre() : $vacuna->getNombre().' (inactiva)',
                'query_builder' => static function (VacunaRepository $repository) use ($organizacion, $vacunaActual) {
                    $queryBuilder = $repository->createActiveOrganizationQueryBuilder($organizacion)
                        ->orderBy('vacuna.nombre', SortDirection::Ascending);
                    if (null !== $vacunaActual) {
                        $queryBuilder->orWhere('vacuna = :vacunaActual')->setParameter('vacunaActual', $vacunaActual);
                    }

                    return $queryBuilder;
                },
                'placeholder' => 'Selecciona una vacuna',
                'constraints' => [new Assert\NotNull(message: 'Selecciona una vacuna.')],
            ])
            ->add('fecha', DateType::class, [
                'label' => 'Fecha de vacunación',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotBlank(message: 'Indica la fecha de vacunación.'),
                    new Assert\LessThanOrEqual(value: 'today', message: 'La fecha de vacunación no puede estar en el futuro.'),
                ],
            ])
            ->add('dosis', TextType::class, [
                'label' => 'Dosis',
                'empty_data' => '',
                'help' => 'Por ejemplo: 5 ml. Hasta 50 caracteres.',
                'constraints' => [
                    new Assert\NotBlank(message: 'La dosis es obligatoria.'),
                    new Assert\Length(max: 50, maxMessage: 'La dosis admite hasta 50 caracteres.'),
                ],
            ])
            ->add('observaciones', TextareaType::class, [
                'label' => 'Observaciones',
                'required' => false,
                'help' => 'Opcional. Hasta 200 caracteres.',
                'attr' => ['maxlength' => 200],
                'constraints' => [new Assert\Length(max: 200, maxMessage: 'Las observaciones admiten hasta {{ limit }} caracteres.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'elegir_animal' => true, 'vacuna_actual' => null]);
        $resolver->setRequired('usuario');
        $resolver->setAllowedTypes('usuario', Usuario::class);
        $resolver->setAllowedTypes('elegir_animal', 'bool');
        $resolver->setAllowedTypes('vacuna_actual', ['null', Vacuna::class]);
    }
}
