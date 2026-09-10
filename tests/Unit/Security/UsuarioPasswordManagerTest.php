<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Usuario;
use App\Security\UsuarioPasswordManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UsuarioPasswordManagerTest extends TestCase
{
    public function testItRejectsPasswordsShorterThanEightCharacters(): void
    {
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $user = new Usuario('Yahir', 'yahir@example.com', '', Usuario::ROL_ADMINISTRADOR);

        $this->expectException(InvalidArgumentException::class);

        (new UsuarioPasswordManager($passwordHasher))->setPassword($user, 'short');
    }

    public function testItHashesValidPasswordsBeforeStoringThem(): void
    {
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())
            ->method('hashPassword')
            ->willReturn('$2y$hashed-password');
        $user = new Usuario('Yahir', 'yahir@example.com', '', Usuario::ROL_ADMINISTRADOR);

        (new UsuarioPasswordManager($passwordHasher))->setPassword($user, 'eightchars');

        self::assertSame('$2y$hashed-password', $user->getPassword());
    }
}
