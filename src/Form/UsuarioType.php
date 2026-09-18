<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use App\Repository\FincaRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use SortDirection;

/** @extends AbstractType<Usuario> */
final class UsuarioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordConstraints = [
            new Assert\Length(
                min: 8,
                max: 4096,
                minMessage: 'La contraseña debe contener al menos {{ limit }} caracteres.',
            ),
        ];

        if ($options['require_password']) {
            $passwordConstraints[] = new Assert\NotBlank(message: 'Ingresa una contraseña.');
        }

        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre completo',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Correo electrónico',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('rol', ChoiceType::class, [
                'label' => 'Rol',
                'choices' => [
                    'Administrador' => Usuario::ROL_ADMINISTRADOR,
                    'Encargado de finca' => Usuario::ROL_ENCARGADO,
                ],
            ])
            ->add('fincaActiva', EntityType::class, [
                'label' => 'Finca asignada',
                'class' => Finca::class,
                'choice_label' => static fn (Finca $finca): string => sprintf('%s · %s', $finca->getNombre(), $finca->getCodigo()),
                'query_builder' => static fn (FincaRepository $repository) => $repository
                    ->createQueryBuilder('finca')
                    ->andWhere('finca.organizacion = :organizacion')
                    ->setParameter('organizacion', $options['organization'])
                    ->orderBy('finca.nombre', SortDirection::Ascending),
                'placeholder' => 'Selecciona una finca',
                'required' => false,
                'help' => 'Es obligatoria para los encargados. Al cambiarla se reemplaza la asignación anterior.',
            ])
            ->add('activo', CheckboxType::class, [
                'label' => 'Cuenta activa',
                'required' => false,
                'help' => 'Las cuentas inactivas no pueden iniciar sesión.',
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['require_password'] ? 'Contraseña' : 'Nueva contraseña',
                'mapped' => false,
                'required' => $options['require_password'],
                'constraints' => $passwordConstraints,
                'help' => $options['require_password'] ? 'Debe contener al menos 8 caracteres.' : 'Déjala vacía para conservar la contraseña actual.',
                'attr' => ['autocomplete' => 'new-password'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Usuario::class,
            'require_password' => false,
        ]);
        $resolver->setRequired('organization');
        $resolver->setAllowedTypes('organization', OrganizacionGanadera::class);
        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
