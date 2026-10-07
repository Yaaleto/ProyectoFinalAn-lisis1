<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Animal;
use App\Entity\Usuario;
use App\Repository\AnimalRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Alta y edición de pesajes (RF-06). El animal solo se elige en el alta; ningún campo recibe la organización.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class PesajeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
            ->add('fecha', DateType::class, [
                'label' => 'Fecha del pesaje',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotBlank(message: 'Indica la fecha del pesaje.'),
                    new Assert\LessThanOrEqual(value: 'today', message: 'La fecha del pesaje no puede estar en el futuro.'),
                ],
            ])
            ->add('pesoKg', NumberType::class, [
                'label' => 'Peso (kg)',
                'input' => 'string',
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => '0.01'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Indica el peso.'),
                    new Assert\Positive(message: 'El peso debe ser mayor que cero.'),
                    new Assert\LessThan(value: 1000000, message: 'El peso no es válido.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'elegir_animal' => true]);
        $resolver->setRequired('usuario');
        $resolver->setAllowedTypes('usuario', Usuario::class);
        $resolver->setAllowedTypes('elegir_animal', 'bool');
    }
}
