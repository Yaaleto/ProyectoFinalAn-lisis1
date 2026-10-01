<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'organizacion_ganadera')]
#[UniqueEntity(fields: ['codigo'], message: 'El código de organización ya está en uso.')]
class OrganizacionGanadera
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_organizacion', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $nombre;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $codigo;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $telefono = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $direccion = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $activo = true;

    /** @var Collection<int, Usuario> */
    #[ORM\OneToMany(mappedBy: 'organizacion', targetEntity: Usuario::class)]
    private Collection $usuarios;

    /** @var Collection<int, Finca> */
    #[ORM\OneToMany(mappedBy: 'organizacion', targetEntity: Finca::class)]
    private Collection $fincas;

    public function __construct(string $nombre, string $codigo)
    {
        $this->nombre = $nombre;
        $this->codigo = $codigo;
        $this->usuarios = new ArrayCollection();
        $this->fincas = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): static
    {
        $this->telefono = $telefono;

        return $this;
    }

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): static
    {
        $this->direccion = $direccion;

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

    /** @return Collection<int, Usuario> */
    public function getUsuarios(): Collection
    {
        return $this->usuarios;
    }

    public function addUsuario(Usuario $usuario): static
    {
        if (!$this->usuarios->contains($usuario)) {
            $this->usuarios->add($usuario);
            $usuario->setOrganizacion($this);
        }

        return $this;
    }

    /** @return Collection<int, Finca> */
    public function getFincas(): Collection
    {
        return $this->fincas;
    }

    public function addFinca(Finca $finca): static
    {
        if (!$this->fincas->contains($finca)) {
            $this->fincas->add($finca);
            $finca->setOrganizacion($this);
        }

        return $this;
    }
}
