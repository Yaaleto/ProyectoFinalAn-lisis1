<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Animal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Cambio de estado del animal (CA-04-C): VENDIDO, FALLECIDO o EGRESADO con fecha de salida, o ACTIVO sin ella.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class SalidaAnimalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('estado', ChoiceType::class, [
                'label' => 'Nuevo estado',
                'choices' => [
                    'Activo' => Animal::ESTADO_ACTIVO,
                    'Vendido' => Animal::ESTADO_VENDIDO,
                    'Fallecido' => Animal::ESTADO_FALLECIDO,
                    'Egresado' => Animal::ESTADO_EGRESADO,
                ],
                'constraints' => [new Assert\NotBlank(message: 'Selecciona el nuevo estado.')],
            ])
            ->add('fechaSalida', DateType::class, [
                'label' => 'Fecha de salida',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'help' => 'Obligatoria para vendido, fallecido o egresado; vacía para activo. No puede ser futura.',
                'constraints' => [new Assert\LessThanOrEqual(value: 'today', message: 'La fecha de salida no puede estar en el futuro.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'constraints' => [new Assert\Callback(self::validarFechaSegunEstado(...))],
        ]);
    }

    /** @param array<string, mixed>|null $data */
    public static function validarFechaSegunEstado(?array $data, ExecutionContextInterface $context): void
    {
        $estado = $data['estado'] ?? null;
        $fecha = $data['fechaSalida'] ?? null;

        if (Animal::ESTADO_ACTIVO === $estado && null !== $fecha) {
            $context->buildViolation('Un animal activo no lleva fecha de salida.')->atPath('[fechaSalida]')->addViolation();
        }
        if (null !== $estado && Animal::ESTADO_ACTIVO !== $estado && null === $fecha) {
            $context->buildViolation('Indica la fecha de salida.')->atPath('[fechaSalida]')->addViolation();
        }
    }
}
