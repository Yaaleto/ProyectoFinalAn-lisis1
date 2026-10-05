<?php

declare(strict_types=1);

namespace App\Entity\Concerns;

use App\Entity\Usuario;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Política de auditoría V1: el alta llena solo los campos de creación; modificado_por y
 * fecha_actualizacion quedan NULL hasta la primera edición real y luego cambian juntos.
 * Los valores los asigna App\EventListener\AuditListener.
 */
trait Auditable
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'creado_por', referencedColumnName: 'id_usuario', nullable: false, onDelete: 'RESTRICT')]
    private ?Usuario $creadoPor = null;

    #[ORM\Column(name: 'fecha_creacion', type: 'datetime_immutable')]
    private ?DateTimeImmutable $fechaCreacion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'modificado_por', referencedColumnName: 'id_usuario', nullable: true, onDelete: 'RESTRICT')]
    private ?Usuario $modificadoPor = null;

    #[ORM\Column(name: 'fecha_actualizacion', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $fechaActualizacion = null;

    public function getCreadoPor(): ?Usuario
    {
        return $this->creadoPor;
    }

    /** Solo para altas fuera de una petición autenticada (cargas administrativas y pruebas). */
    public function setCreadoPor(Usuario $creadoPor): static
    {
        $this->creadoPor = $creadoPor;

        return $this;
    }

    public function getFechaCreacion(): ?DateTimeImmutable
    {
        return $this->fechaCreacion;
    }

    public function getModificadoPor(): ?Usuario
    {
        return $this->modificadoPor;
    }

    public function getFechaActualizacion(): ?DateTimeImmutable
    {
        return $this->fechaActualizacion;
    }

    public function registrarCreacion(Usuario $usuario, DateTimeImmutable $fecha): void
    {
        $this->creadoPor = $usuario;
        $this->fechaCreacion = $fecha;
        $this->modificadoPor = null;
        $this->fechaActualizacion = null;
    }

    public function registrarModificacion(Usuario $usuario, DateTimeImmutable $fecha): void
    {
        $this->modificadoPor = $usuario;
        $this->fechaActualizacion = $fecha;
    }
}
