<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Raza;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Raza> */
final class RazaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('nombre', TextType::class, [
            'label' => 'Nombre',
            'empty_data' => '',
            'help' => 'Debe ser único dentro de la organización.',
            'attr' => ['autocomplete' => 'off'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Raza::class]);
    }
}
