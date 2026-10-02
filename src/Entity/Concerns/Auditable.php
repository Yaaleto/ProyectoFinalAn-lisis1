<?php

declare(strict_types=1);

namespace App\Entity\Concerns;

use App\Entity\Usuario;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

trait Auditable
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'creado_por', referencedColumnName: 'id_usuario', nullable: false, onDelete: 'RESTRICT')]
    private ?Usuario $creadoPor = null;

    #[ORM\Column(name: 'fecha_creacion', type: 'datetime_immutable')]
    private ?DateTimeImmutable $fechaCreacion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'modificado_por', referencedColumnName: 'id_usuario', nullable: true, onDelete: 'RESTRICT')]
    private ?Usuario $actualizadoPor = null;

    #[ORM\Column(name: 'fecha_actualizacion', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $fechaActualizacion = null;

    public function getCreadoPor(): ?Usuario
    {
        return $this->creadoPor;
    }

    public function setCreadoPor(?Usuario $creadoPor): static
    {
        $this->creadoPor = $creadoPor;

        return $this;
    }

    public function getFechaCreacion(): ?DateTimeImmutable
    {
        return $this->fechaCreacion;
    }

    public function getActualizadoPor(): ?Usuario
    {
        return $this->actualizadoPor;
    }

    public function setActualizadoPor(?Usuario $actualizadoPor): static
    {
        $this->actualizadoPor = $actualizadoPor;

        return $this;
    }

    public function getFechaActualizacion(): ?DateTimeImmutable
    {
        return $this->fechaActualizacion;
    }

    #[ORM\PrePersist]
    public function initializeAuditDates(): void
    {
        $now = new DateTimeImmutable();
        $this->fechaCreacion ??= $now;
        $this->fechaActualizacion = $now;
    }

    #[ORM\PreUpdate]
    public function updateAuditDate(): void
    {
        $this->fechaActualizacion = new DateTimeImmutable();
    }
}
