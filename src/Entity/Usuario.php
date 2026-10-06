<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UsuarioRepository;
use DomainException;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[ORM\Table(name: 'usuario')]
#[UniqueEntity(fields: ['email'], message: 'El correo electrónico ya está en uso.')]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROL_ADMINISTRADOR = 'ADMINISTRADOR';
    public const ROL_ENCARGADO = 'ENCARGADO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_usuario', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'usuarios')]
    #[ORM\JoinColumn(name: 'id_organizacion', referencedColumnName: 'id_organizacion', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?OrganizacionGanadera $organizacion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'finca_activa_id', referencedColumnName: 'id_finca', nullable: true, onDelete: 'RESTRICT')]
    private ?Finca $fincaActiva = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $nombre;

    #[ORM\Column(name: 'correo', length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email;

    #[ORM\Column(name: 'contrasena_hash', length: 255)]
    private string $contrasenaHash;

    #[ORM\Column(length: 15)]
    #[Assert\Choice(choices: [self::ROL_ADMINISTRADOR, self::ROL_ENCARGADO])]
    private string $rol;

    #[ORM\Column(options: ['default' => true])]
    private bool $activo = true;

    public function __construct(string $nombre, string $email, string $contrasenaHash, string $rol)
    {
        $this->nombre = $nombre;
        $this->email = $email;
        $this->contrasenaHash = $contrasenaHash;
        $this->rol = $rol;
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
        if (null !== $this->fincaActiva && $this->fincaActiva->getOrganizacion() !== $organizacion) {
            throw new DomainException('La finca activa debe pertenecer a la organización del usuario.');
        }

        $this->organizacion = $organizacion;

        return $this;
    }

    public function getFincaActiva(): ?Finca
    {
        return $this->fincaActiva;
    }

    public function setFincaActiva(?Finca $fincaActiva): static
    {
        if (null !== $fincaActiva && null !== $this->organizacion && $fincaActiva->getOrganizacion() !== $this->organizacion) {
            throw new DomainException('La finca activa debe pertenecer a la organización del usuario.');
        }

        $this->fincaActiva = $fincaActiva;

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return match ($this->rol) {
            self::ROL_ADMINISTRADOR => ['ROLE_ADMINISTRADOR'],
            self::ROL_ENCARGADO => ['ROLE_ENCARGADO'],
            default => [],
        };
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getContrasenaHash(): string
    {
        return $this->contrasenaHash;
    }

    public function getPassword(): string
    {
        return $this->contrasenaHash;
    }

    public function setContrasenaHash(string $contrasenaHash): static
    {
        $this->contrasenaHash = $contrasenaHash;

        return $this;
    }

    public function getRol(): string
    {
        return $this->rol;
    }

    public function setRol(string $rol): static
    {
        $this->rol = $rol;

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

    #[Assert\Callback]
    public function validateActiveFarm(ExecutionContextInterface $context): void
    {
        if (self::ROL_ENCARGADO === $this->rol && null === $this->fincaActiva) {
            $context
                ->buildViolation('Selecciona una finca activa para el encargado.')
                ->atPath('fincaActiva')
                ->addViolation();
        }
        if (self::ROL_ENCARGADO === $this->rol && null !== $this->fincaActiva && !$this->fincaActiva->isActivo()) {
            $context
                ->buildViolation('La finca asignada debe estar activa.')
                ->atPath('fincaActiva')
                ->addViolation();
        }
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
