<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RazaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RazaRepository::class)]
#[ORM\Table(name: 'raza')]
#[ORM\UniqueConstraint(name: 'uq_raza_organizacion_nombre', columns: ['id_organizacion', 'nombre'])]
#[ORM\UniqueConstraint(name: 'uq_raza_id_organizacion', columns: ['id_raza', 'id_organizacion'])]
#[UniqueEntity(fields: ['organizacion', 'nombre'], message: 'La raza ya existe en la organización.')]
class Raza
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_raza', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_organizacion', referencedColumnName: 'id_organizacion', nullable: false, onDelete: 'RESTRICT')]
    private OrganizacionGanadera $organizacion;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $nombre;

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
    public function isActiva(): bool { return $this->activa; }
    public function setActiva(bool $activa): static { $this->activa = $activa; return $this; }
}
