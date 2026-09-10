<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Usuario;
use InvalidArgumentException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UsuarioPasswordManager
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function setPassword(Usuario $user, string $plainPassword): void
    {
        if (strlen($plainPassword) < 8) {
            throw new InvalidArgumentException('La contraseña debe tener al menos ocho caracteres.');
        }

        $user->setContrasenaHash($this->passwordHasher->hashPassword($user, $plainPassword));
    }
}
