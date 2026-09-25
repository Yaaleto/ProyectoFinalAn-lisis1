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
                'required' => false,
                'constraints' => [new Assert\Length(max: 50)],
                'help' => 'Debe ser único dentro de la organización.',
                'attr' => ['autocomplete' => 'off'],
            ])
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'required' => false,
                'constraints' => [new Assert\Length(max: 120)],
            ])
            ->add('finca', EntityType::class, [
                'label' => 'Finca',
                'class' => Finca::class,
                'choice_label' => static fn (Finca $finca): string => sprintf('%s · %s', $finca->getNombre(), $finca->getCodigo()),
                'query_builder' => static fn (FincaRepository $repository) => $repository
                    ->createAuthorizedQueryBuilder($options['user'])
                    ->orderBy('finca.nombre', SortDirection::Ascending),
                'placeholder' => 'Selecciona una finca',
            ])
            ->add('raza', EntityType::class, [
                'label' => 'Raza',
                'class' => Raza::class,
                'choice_label' => 'nombre',
                'query_builder' => static fn (RazaRepository $repository) => $repository
                    ->createActiveOrganizationQueryBuilder($options['organization'])
                    ->orderBy('raza.nombre', SortDirection::Ascending),
                'placeholder' => 'Sin raza',
                'required' => false,
            ])
            ->add('sexo', ChoiceType::class, [
                'label' => 'Sexo',
                'choices' => ['Hembra' => Animal::SEXO_HEMBRA, 'Macho' => Animal::SEXO_MACHO],
            ])
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
                'constraints' => [new Assert\Positive(message: 'El peso al nacimiento debe ser mayor que cero.')],
            ])
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
            ]);
        }

        if (Animal::ORIGEN_NACIDO_EN_FINCA !== $options['intake_origin']) {
            $builder->add('procedencia', TextType::class, [
                'label' => 'Procedencia',
                'required' => false,
                'help' => 'Opcional. Indica la finca o lugar de procedencia.',
                'constraints' => [new Assert\Length(max: 255)],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'intake_origin' => null]);
        $resolver->setRequired(['organization', 'user']);
        $resolver->setAllowedTypes('organization', OrganizacionGanadera::class);
        $resolver->setAllowedTypes('user', Usuario::class);
        $resolver->setAllowedValues('intake_origin', [null, Animal::ORIGEN_NACIDO_EN_FINCA, Animal::ORIGEN_EXTERNO]);
    }
}
