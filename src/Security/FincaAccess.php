<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;

final class FincaAccess
{
    public function canView(Usuario $usuario, Finca $finca): bool
    {
        if (!$this->belongsToUserOrganization($usuario, $finca)) {
            return false;
        }

        return match ($usuario->getRol()) {
            Usuario::ROL_ADMINISTRADOR => true,
            Usuario::ROL_ENCARGADO => $this->sameFarm($usuario->getFincaActiva(), $finca),
            default => false,
        };
    }

    public function canManage(Usuario $usuario, Finca $finca): bool
    {
        return Usuario::ROL_ADMINISTRADOR === $usuario->getRol()
            && $this->belongsToUserOrganization($usuario, $finca);
    }

    public function canAccessOrganization(Usuario $usuario, OrganizacionGanadera $organizacion): bool
    {
        return $this->sameOrganization($usuario->getOrganizacion(), $organizacion);
    }

    private function belongsToUserOrganization(Usuario $usuario, Finca $finca): bool
    {
        return $this->sameOrganization($usuario->getOrganizacion(), $finca->getOrganizacion());
    }

    private function sameOrganization(?OrganizacionGanadera $first, ?OrganizacionGanadera $second): bool
    {
        return null !== $first
            && null !== $second
            && ($first === $second || (null !== $first->getId() && $first->getId() === $second->getId()));
    }

    private function sameFarm(?Finca $first, Finca $second): bool
    {
        return null !== $first
            && ($first === $second || (null !== $first->getId() && $first->getId() === $second->getId()));
    }
}
