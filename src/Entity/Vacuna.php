<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\Auditable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\VacunaRepository::class)]
#[ORM\Table(name: 'vacuna')]
#[ORM\UniqueConstraint(name: 'uniq_vacuna_organizacion_nombre', columns: ['organizacion_id', 'nombre'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['organizacion', 'nombre'], message: 'La vacuna ya existe en la organización.')]
class Vacuna
{
    use Auditable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private OrganizacionGanadera $organizacion;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $nombre;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $activa = true;

    public function __construct(OrganizacionGanadera $organizacion, string $nombre)
    {
        $this->organizacion = $organizacion;
        $this->nombre = $nombre;
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganizacion(): OrganizacionGanadera { return $this->organizacion; }
    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $nombre): static { $this->nombre = $nombre; return $this; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): static { $this->descripcion = $descripcion; return $this; }
    public function isActiva(): bool { return $this->activa; }
    public function setActiva(bool $activa): static { $this->activa = $activa; return $this; }
}
