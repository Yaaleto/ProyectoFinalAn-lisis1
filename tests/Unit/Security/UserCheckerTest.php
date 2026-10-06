<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class UserCheckerTest extends TestCase
{
    public function testActiveUserExposesTheRoleAssignedByTheDomain(): void
    {
        $user = new Usuario('Yahir', 'yahir@example.com', '$2y$test', Usuario::ROL_ADMINISTRADOR);

        self::assertSame(['ROLE_ADMINISTRADOR'], $user->getRoles());
    }

    public function testInactiveUserIsRejectedBeforeAuthentication(): void
    {
        $user = (new Usuario('Yahir', 'yahir@example.com', '$2y$test', Usuario::ROL_ENCARGADO))->setActivo(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        (new UserChecker())->checkPreAuth($user);
    }

    public function testActiveUserOfAnActiveOrganizationPassesTheCheck(): void
    {
        $user = (new Usuario('Yahir', 'yahir@example.com', '$2y$test', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion(new OrganizacionGanadera('Norte', 'NORTE'));

        (new UserChecker())->checkPreAuth($user);

        $this->addToAssertionCount(1);
    }

    public function testInactiveOrganizationIsRejectedBeforeAuthentication(): void
    {
        $organization = (new OrganizacionGanadera('Norte', 'NORTE'))->setActivo(false);
        $user = (new Usuario('Yahir', 'yahir@example.com', '$2y$test', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organization);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Esta organización está inactiva.');

        (new UserChecker())->checkPreAuth($user);
    }
}
