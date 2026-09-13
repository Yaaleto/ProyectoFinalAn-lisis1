<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use App\Security\FincaAccess;
use PHPUnit\Framework\TestCase;

final class FincaAccessTest extends TestCase
{
    private FincaAccess $fincaAccess;

    protected function setUp(): void
    {
        $this->fincaAccess = new FincaAccess();
    }

    public function testAdministratorCanViewAndManageAnyFarmInItsOrganization(): void
    {
        $organizacion = new OrganizacionGanadera('Ganadería Norte', 'NORTE');
        $finca = (new Finca('Finca Central', 'CENTRAL'))->setOrganizacion($organizacion);
        $administrador = (new Usuario('Admin', 'admin@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);

        self::assertTrue($this->fincaAccess->canView($administrador, $finca));
        self::assertTrue($this->fincaAccess->canManage($administrador, $finca));
    }

    public function testFarmManagerCanOnlyViewItsAssignedFarm(): void
    {
        $organizacion = new OrganizacionGanadera('Ganadería Norte', 'NORTE');
        $fincaAsignada = (new Finca('Finca Central', 'CENTRAL'))->setOrganizacion($organizacion);
        $otraFinca = (new Finca('Finca Oriente', 'ORIENTE'))->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado', 'manager@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($fincaAsignada);

        self::assertTrue($this->fincaAccess->canView($encargado, $fincaAsignada));
        self::assertFalse($this->fincaAccess->canView($encargado, $otraFinca));
        self::assertFalse($this->fincaAccess->canManage($encargado, $fincaAsignada));
    }

    public function testUserCannotAccessFarmFromAnotherOrganization(): void
    {
        $organizacion = new OrganizacionGanadera('Ganadería Norte', 'NORTE');
        $otraOrganizacion = new OrganizacionGanadera('Ganadería Sur', 'SUR');
        $fincaAjena = (new Finca('Finca Sur', 'SUR-01'))->setOrganizacion($otraOrganizacion);
        $administrador = (new Usuario('Admin', 'admin@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);

        self::assertFalse($this->fincaAccess->canView($administrador, $fincaAjena));
        self::assertFalse($this->fincaAccess->canManage($administrador, $fincaAjena));
        self::assertFalse($this->fincaAccess->canAccessOrganization($administrador, $otraOrganizacion));
    }

    public function testFarmManagerWithoutAssignmentCannotViewAFarm(): void
    {
        $organizacion = new OrganizacionGanadera('Ganadería Norte', 'NORTE');
        $finca = (new Finca('Finca Central', 'CENTRAL'))->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado', 'manager@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion);

        self::assertFalse($this->fincaAccess->canView($encargado, $finca));
    }
}
