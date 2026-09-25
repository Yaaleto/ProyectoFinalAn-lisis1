<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\Auditable;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DomainException;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'animal')]
#[ORM\UniqueConstraint(name: 'uniq_animal_organizacion_arete', columns: ['organizacion_id', 'arete'])]
#[ORM\Index(name: 'idx_animal_finca_estado', columns: ['finca_id', 'estado'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['codigoInterno'], message: 'El código interno ya existe.')]
#[UniqueEntity(fields: ['organizacion', 'arete'], message: 'El arete ya existe en la organización.')]
class Animal
{
    use Auditable;

    public const SEXO_HEMBRA = 'HEMBRA';
    public const SEXO_MACHO = 'MACHO';
    public const ORIGEN_NACIDO_EN_FINCA = 'NACIDO_EN_FINCA';
    public const ORIGEN_EXTERNO = 'EXTERNO';
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_VENDIDO = 'VENDIDO';
    public const ESTADO_FALLECIDO = 'FALLECIDO';
    public const ESTADO_EGRESADO = 'EGRESADO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private OrganizacionGanadera $organizacion;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Finca $finca;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Raza $raza = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'hijosComoPadre')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?self $padre = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'hijosComoMadre')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?self $madre = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(mappedBy: 'padre', targetEntity: self::class)]
    private Collection $hijosComoPadre;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(mappedBy: 'madre', targetEntity: self::class)]
    private Collection $hijosComoMadre;

    #[ORM\Column(length: 36, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 36)]
    private string $codigoInterno;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $arete = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $nombre = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: [self::SEXO_HEMBRA, self::SEXO_MACHO])]
    private string $sexo;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $fechaNacimiento = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $pesoNacimientoKg = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::ORIGEN_NACIDO_EN_FINCA, self::ORIGEN_EXTERNO])]
    private string $origen;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $procedencia = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $productoraLeche = false;

    #[ORM\Column(length: 15, options: ['default' => self::ESTADO_ACTIVO])]
    #[Assert\Choice(choices: [self::ESTADO_ACTIVO, self::ESTADO_VENDIDO, self::ESTADO_FALLECIDO, self::ESTADO_EGRESADO])]
    private string $estado = self::ESTADO_ACTIVO;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $fechaSalida = null;

    public function __construct(OrganizacionGanadera $organizacion, Finca $finca, string $codigoInterno, string $sexo, string $origen)
    {
        $codigoInterno = trim($codigoInterno);
        if ('' === $codigoInterno || strlen($codigoInterno) > 36) {
            throw new DomainException('El código interno del animal no es válido.');
        }
        if ($finca->getOrganizacion() !== $organizacion) {
            throw new DomainException('La finca debe pertenecer a la organización del animal.');
        }
        if (!in_array($sexo, [self::SEXO_HEMBRA, self::SEXO_MACHO], true)) {
            throw new DomainException('El sexo del animal no es válido.');
        }
        if (!in_array($origen, [self::ORIGEN_NACIDO_EN_FINCA, self::ORIGEN_EXTERNO], true)) {
            throw new DomainException('El origen del animal no es válido.');
        }

        $this->organizacion = $organizacion;
        $this->finca = $finca;
        $this->codigoInterno = $codigoInterno;
        $this->sexo = $sexo;
        $this->origen = $origen;
        $this->hijosComoPadre = new ArrayCollection();
        $this->hijosComoMadre = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganizacion(): OrganizacionGanadera { return $this->organizacion; }
    public function getFinca(): Finca { return $this->finca; }
    public function getRaza(): ?Raza { return $this->raza; }
    public function getPadre(): ?self { return $this->padre; }
    public function getMadre(): ?self { return $this->madre; }
    public function getCodigoInterno(): string { return $this->codigoInterno; }
    public function getArete(): ?string { return $this->arete; }
    public function getNombre(): ?string { return $this->nombre; }
    public function getSexo(): string { return $this->sexo; }
    public function getFechaNacimiento(): ?DateTimeImmutable { return $this->fechaNacimiento; }
    public function getPesoNacimientoKg(): ?string { return $this->pesoNacimientoKg; }
    public function getOrigen(): string { return $this->origen; }
    public function getProcedencia(): ?string { return $this->procedencia; }
    public function isProductoraLeche(): bool { return $this->productoraLeche; }
    public function getEstado(): string { return $this->estado; }
    public function getFechaSalida(): ?DateTimeImmutable { return $this->fechaSalida; }

    public function setFinca(Finca $finca): static
    {
        if ($finca->getOrganizacion() !== $this->organizacion) {
            throw new DomainException('La finca debe pertenecer a la organización del animal.');
        }
        $this->finca = $finca;
        return $this;
    }

    public function setRaza(?Raza $raza): static
    {
        if (null !== $raza && $raza->getOrganizacion() !== $this->organizacion) {
            throw new DomainException('La raza debe pertenecer a la organización del animal.');
        }
        if (null !== $raza && !$raza->isActiva()) {
            throw new DomainException('La raza debe estar activa.');
        }
        $this->raza = $raza;
        return $this;
    }

    public function setPadre(?self $padre): static
    {
        $this->assertProgenitor($padre, self::SEXO_MACHO, 'padre');
        if ($this->padre === $padre) {
            return $this;
        }
        $this->padre?->hijosComoPadre->removeElement($this);
        $this->padre = $padre;
        $padre?->hijosComoPadre->add($this);
        return $this;
    }

    public function setMadre(?self $madre): static
    {
        $this->assertProgenitor($madre, self::SEXO_HEMBRA, 'madre');
        if ($this->madre === $madre) {
            return $this;
        }
        $this->madre?->hijosComoMadre->removeElement($this);
        $this->madre = $madre;
        $madre?->hijosComoMadre->add($this);
        return $this;
    }

    public function setArete(?string $arete): static { $this->arete = $arete; return $this; }
    public function setNombre(?string $nombre): static { $this->nombre = $nombre; return $this; }
    public function setSexo(string $sexo): static
    {
        if (!in_array($sexo, [self::SEXO_HEMBRA, self::SEXO_MACHO], true)) {
            throw new DomainException('El sexo del animal no es válido.');
        }
        if (self::SEXO_MACHO === $sexo && $this->productoraLeche) {
            throw new DomainException('Una productora de leche no puede cambiarse a macho.');
        }
        if (self::SEXO_HEMBRA === $sexo && !$this->hijosComoPadre->isEmpty()) {
            throw new DomainException('No puedes cambiar el sexo de un animal registrado como progenitor.');
        }
        if (self::SEXO_MACHO === $sexo && !$this->hijosComoMadre->isEmpty()) {
            throw new DomainException('No puedes cambiar el sexo de un animal registrado como progenitor.');
        }
        $this->sexo = $sexo;

        return $this;
    }

    public function setFechaNacimiento(?DateTimeImmutable $fecha): static
    {
        if (null !== $fecha && $fecha > new DateTimeImmutable('today')) {
            throw new DomainException('La fecha de nacimiento no puede estar en el futuro.');
        }
        if (null !== $fecha && null !== $this->fechaSalida && $fecha > $this->fechaSalida) {
            throw new DomainException('La fecha de nacimiento no puede ser posterior a la salida.');
        }
        $this->fechaNacimiento = $fecha;

        return $this;
    }

    public function setPesoNacimientoKg(?string $peso): static
    {
        if (null !== $peso && (!is_numeric($peso) || (float) $peso <= 0)) {
            throw new DomainException('El peso al nacimiento debe ser mayor que cero.');
        }
        $this->pesoNacimientoKg = $peso;
        return $this;
    }

    public function setProcedencia(?string $procedencia): static { $this->procedencia = $procedencia; return $this; }

    public function setOrigen(string $origen): static
    {
        if (!in_array($origen, [self::ORIGEN_NACIDO_EN_FINCA, self::ORIGEN_EXTERNO], true)) {
            throw new DomainException('El origen del animal no es válido.');
        }
        $this->origen = $origen;

        return $this;
    }

    public function setProductoraLeche(bool $productora): static
    {
        if ($productora && self::SEXO_HEMBRA !== $this->sexo) {
            throw new DomainException('Solo una hembra puede ser productora de leche.');
        }
        $this->productoraLeche = $productora;
        return $this;
    }

    public function registrarSalida(string $estado, DateTimeImmutable $fecha): static
    {
        if (!in_array($estado, [self::ESTADO_VENDIDO, self::ESTADO_FALLECIDO, self::ESTADO_EGRESADO], true)) {
            throw new DomainException('El estado de salida no es válido.');
        }
        if ($fecha > new DateTimeImmutable('today')) {
            throw new DomainException('La fecha de salida no puede estar en el futuro.');
        }
        if (null !== $this->fechaNacimiento && $fecha < $this->fechaNacimiento) {
            throw new DomainException('La salida no puede preceder al nacimiento.');
        }
        $this->estado = $estado;
        $this->fechaSalida = $fecha;
        return $this;
    }

    public function assertFechaOperativa(DateTimeInterface $fecha): void
    {
        if (null !== $this->fechaNacimiento && $fecha < $this->fechaNacimiento) {
            throw new DomainException('La fecha no puede preceder al nacimiento.');
        }
        if (null !== $this->fechaSalida && $fecha > $this->fechaSalida) {
            throw new DomainException('No se permiten registros posteriores a la salida del animal.');
        }
    }

    private function assertProgenitor(?self $progenitor, string $sexo, string $rol): void
    {
        if (null === $progenitor) {
            return;
        }
        if ($progenitor->organizacion !== $this->organizacion) {
            throw new DomainException(sprintf('El %s debe pertenecer a la misma organización.', $rol));
        }
        if ($progenitor->sexo !== $sexo) {
            throw new DomainException(sprintf('El sexo del %s no es válido.', $rol));
        }

        $pendientes = [$progenitor];
        $visitados = [];
        while ($pendientes) {
            $ancestro = array_pop($pendientes);
            if ($ancestro === $this || (null !== $this->id && $ancestro->id === $this->id)) {
                throw new DomainException('Un animal no puede ser su propio progenitor ni descendiente.');
            }
            $identificador = spl_object_id($ancestro);
            if (isset($visitados[$identificador])) {
                continue;
            }
            $visitados[$identificador] = true;
            foreach ([$ancestro->padre, $ancestro->madre] as $progenitorAnterior) {
                if (null !== $progenitorAnterior) {
                    $pendientes[] = $progenitorAnterior;
                }
            }
        }
    }
}
