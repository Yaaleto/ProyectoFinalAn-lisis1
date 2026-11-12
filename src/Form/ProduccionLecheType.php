<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\Usuario;
use App\Repository\AnimalRepository;
use App\Repository\FincaRepository;
use SortDirection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Alta y edición de ordeños (RF-09). Solo se ofrecen hembras activas productoras; la finca del ordeño
 * se propone a partir de la finca actual del animal y el usuario la confirma (CA-09-D, CA-09-E).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ProduccionLecheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $usuario = $options['usuario'];

        if ($options['elegir_animal']) {
            $builder->add('animal', EntityType::class, [
                'label' => 'Hembra productora',
                'class' => Animal::class,
                'choice_label' => static fn (Animal $animal): string => sprintf('%s · %s · %s', $animal->getArete(), $animal->getNombre() ?? 'Sin nombre', $animal->getFinca()->getNombre()),
                'choice_attr' => static fn (Animal $animal): array => ['data-finca-actual' => (string) $animal->getFinca()->getId()],
                'query_builder' => static fn (AnimalRepository $repository) => $repository->createRegistrableQueryBuilder($usuario)
                    ->andWhere('animal.sexo = :sexoHembra')
                    ->andWhere('animal.productoraLeche = :productora')
                    ->andWhere('animal.estado = :estadoActivo')
                    ->setParameter('sexoHembra', Animal::SEXO_HEMBRA)
                    ->setParameter('productora', true)
                    ->setParameter('estadoActivo', Animal::ESTADO_ACTIVO),
                'placeholder' => 'Selecciona una hembra productora',
                'attr' => ['data-milk-animal' => ''],
                'constraints' => [new Assert\NotNull(message: 'Selecciona la hembra productora.')],
            ]);
        }

        $builder
            ->add('fincaOrdeno', EntityType::class, [
                'label' => 'Finca donde ocurrió el ordeño',
                'class' => Finca::class,
                'choice_label' => static fn (Finca $finca): string => sprintf('%s · %s', $finca->getNombre(), $finca->getCodigo()),
                'query_builder' => static fn (FincaRepository $repository) => $repository->createAuthorizedQueryBuilder($usuario)
                    ->andWhere('finca.activo = :activa')
                    ->setParameter('activa', true)
                    ->orderBy('finca.nombre', SortDirection::Ascending),
                'placeholder' => 'Confirma la finca del ordeño',
                'help' => 'Se propone la finca actual del animal. Si el ordeño ocurrió antes de un traslado, elige la finca donde ocurrió.',
                'attr' => ['data-milk-farm' => ''],
                'constraints' => [new Assert\NotNull(message: 'Confirma la finca donde ocurrió el ordeño.')],
            ])
            ->add('fechaHora', DateTimeType::class, [
                'label' => 'Fecha y hora del ordeño',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotBlank(message: 'Indica la fecha y hora del ordeño.'),
                    new Assert\LessThanOrEqual(value: 'now', message: 'La fecha y hora del ordeño no pueden estar en el futuro.'),
                ],
            ])
            ->add('cantidadLitros', NumberType::class, [
                'label' => 'Cantidad (litros)',
                'input' => 'string',
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => '0.01', 'max' => '999.99'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Indica la cantidad de leche.'),
                    new Assert\Positive(message: 'La cantidad de leche debe ser mayor que cero.'),
                    new Assert\LessThanOrEqual(value: 999.99, message: 'La cantidad admite hasta 999.99 L.'),
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
