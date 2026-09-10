<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use DomainException;
use PHPUnit\Framework\TestCase;

final class TenantModelTest extends TestCase
{
    public function testOrganizationOwnsItsUsersAndFarms(): void
    {
        $organization = new OrganizacionGanadera('Ganadería Norte', 'NORTE');
        $farm = new Finca('Finca Central', 'CENTRAL');
        $user = new Usuario('Yahir', 'yahir@example.com', '$2y$test', Usuario::ROL_ADMINISTRADOR);

        $organization->addFinca($farm)->addUsuario($user);
        $user->setFincaActiva($farm);

        self::assertSame($organization, $farm->getOrganizacion());
        self::assertSame($organization, $user->getOrganizacion());
        self::assertSame($farm, $user->getFincaActiva());
    }

    public function testUserCannotUseAFarmFromAnotherOrganization(): void
    {
        $organization = new OrganizacionGanadera('Ganadería Norte', 'NORTE');
        $otherOrganization = new OrganizacionGanadera('Ganadería Sur', 'SUR');
        $otherFarm = (new Finca('Finca Sur', 'SUR-01'))->setOrganizacion($otherOrganization);
        $user = (new Usuario('Yahir', 'yahir@example.com', '$2y$test', Usuario::ROL_ENCARGADO))->setOrganizacion($organization);

        $this->expectException(DomainException::class);

        $user->setFincaActiva($otherFarm);
    }

    public function testUserCannotChangeToAnOrganizationDifferentFromItsActiveFarm(): void
    {
        $organization = new OrganizacionGanadera('Ganadería Norte', 'NORTE');
        $otherOrganization = new OrganizacionGanadera('Ganadería Sur', 'SUR');
        $farm = (new Finca('Finca Norte', 'NORTE-01'))->setOrganizacion($organization);
        $user = (new Usuario('Yahir', 'yahir@example.com', '$2y$test', Usuario::ROL_ENCARGADO))->setFincaActiva($farm);

        $this->expectException(DomainException::class);

        $user->setOrganizacion($otherOrganization);
    }

    public function testAuditDatesAreInitializedBeforePersistence(): void
    {
        $organization = new OrganizacionGanadera('Ganadería Norte', 'NORTE');

        $organization->initializeAuditDates();

        self::assertNotNull($organization->getFechaCreacion());
        self::assertSame($organization->getFechaCreacion(), $organization->getFechaActualizacion());
    }
}
