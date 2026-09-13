<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\Auditable;
use App\Repository\FincaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FincaRepository::class)]
#[ORM\Table(name: 'finca')]
#[ORM\UniqueConstraint(name: 'uniq_finca_organizacion_codigo', columns: ['organizacion_id', 'codigo'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['organizacion', 'codigo'], message: 'El código de finca ya existe en la organización.')]
class Finca
{
    use Auditable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'fincas')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?OrganizacionGanadera $organizacion = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $nombre;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $codigo;

    public function __construct(string $nombre, string $codigo)
    {
        $this->nombre = $nombre;
        $this->codigo = $codigo;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizacion(): ?OrganizacionGanadera
    {
        return $this->organizacion;
    }

    public function setOrganizacion(OrganizacionGanadera $organizacion): static
    {
        $this->organizacion = $organizacion;

        return $this;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = $codigo;

        return $this;
    }
}
