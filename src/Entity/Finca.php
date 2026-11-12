<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FincaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FincaRepository::class)]
#[ORM\Table(name: 'finca')]
#[ORM\UniqueConstraint(name: 'uq_finca_organizacion_codigo', columns: ['id_organizacion', 'codigo'])]
#[ORM\UniqueConstraint(name: 'uq_finca_id_organizacion', columns: ['id_finca', 'id_organizacion'])]
#[UniqueEntity(fields: ['organizacion', 'codigo'], message: 'El código de finca ya existe en la organización.')]
class Finca
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_finca', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'fincas')]
    #[ORM\JoinColumn(name: 'id_organizacion', referencedColumnName: 'id_organizacion', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?OrganizacionGanadera $organizacion = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Ingresa el nombre de la finca.')]
    #[Assert\Length(max: 150, maxMessage: 'El nombre admite hasta {{ limit }} caracteres.')]
    private string $nombre;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Ingresa el código de la finca.')]
    #[Assert\Length(max: 50, maxMessage: 'El código admite hasta {{ limit }} caracteres.')]
    private string $codigo;

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200, maxMessage: 'La ubicación admite hasta {{ limit }} caracteres.')]
    private ?string $ubicacion = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $activo = true;

    public function __construct(string $nombre, string $codigo)
    {
        $this->nombre = $nombre;
        $this->setCodigo($codigo);
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

    /** El código se guarda en mayúsculas (CA-02-C). */
    public function setCodigo(string $codigo): static
    {
        $this->codigo = mb_strtoupper(trim($codigo));

        return $this;
    }

    public function getUbicacion(): ?string
    {
        return $this->ubicacion;
    }

    public function setUbicacion(?string $ubicacion): static
    {
        $this->ubicacion = $ubicacion;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }
}
