<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Animal;
use App\Entity\Usuario;
use App\Repository\AnimalRepository;
use SortDirection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Alta y edición de montas (RF-08). La hembra sale del ámbito del usuario; el macho es opcional
 * y puede ser de cualquier finca de la organización (CA-08-D). Ningún campo recibe la organización.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class MontaReproductivaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $usuario = $options['usuario'];
        $etiqueta = static fn (Animal $animal): string => sprintf('%s · %s · %s', $animal->getArete(), $animal->getNombre() ?? 'Sin nombre', $animal->getFinca()->getNombre());

        if ($options['elegir_hembra']) {
            $builder->add('hembra', EntityType::class, [
                'label' => 'Hembra',
                'class' => Animal::class,
                'choice_label' => $etiqueta,
                'query_builder' => static fn (AnimalRepository $repository) => $repository->createRegistrableQueryBuilder($usuario)
                    ->andWhere('animal.sexo = :sexoHembra')
                    ->setParameter('sexoHembra', Animal::SEXO_HEMBRA),
                'placeholder' => 'Selecciona una hembra',
                'constraints' => [new Assert\NotNull(message: 'Selecciona la hembra.')],
            ]);
        }

        $builder
            ->add('macho', EntityType::class, [
                'label' => 'Macho',
                'class' => Animal::class,
                'choice_label' => $etiqueta,
                'query_builder' => static fn (AnimalRepository $repository) => $repository->createQueryBuilder('macho')
                    ->addSelect('fincaMacho')
                    ->join('macho.finca', 'fincaMacho')
                    ->andWhere('macho.organizacion = :organizacion')
                    ->andWhere('macho.sexo = :sexoMacho')
                    ->setParameter('organizacion', $usuario->getOrganizacion())
                    ->setParameter('sexoMacho', Animal::SEXO_MACHO)
                    ->orderBy('macho.arete', SortDirection::Ascending),
                'placeholder' => 'Sin macho registrado',
                'required' => false,
                'help' => 'Opcional. Puede ser de otra finca de la organización.',
            ])
            ->add('fecha', DateType::class, [
                'label' => 'Fecha de la monta',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotBlank(message: 'Indica la fecha de la monta.'),
                    new Assert\LessThanOrEqual(value: 'today', message: 'La fecha de la monta no puede estar en el futuro.'),
                ],
            ])
            ->add('observacion', TextareaType::class, [
                'label' => 'Observación',
                'required' => false,
                'help' => 'Opcional. Hasta 200 caracteres.',
                'attr' => ['maxlength' => 200],
                'constraints' => [new Assert\Length(max: 200, maxMessage: 'La observación admite hasta {{ limit }} caracteres.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'elegir_hembra' => true]);
        $resolver->setRequired('usuario');
        $resolver->setAllowedTypes('usuario', Usuario::class);
        $resolver->setAllowedTypes('elegir_hembra', 'bool');
    }
}
