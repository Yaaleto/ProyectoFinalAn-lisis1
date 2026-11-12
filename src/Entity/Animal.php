<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\Auditable;
use App\Entity\Concerns\AuditableInterface;
use App\Repository\AnimalRepository;
use App\Validation\FechaFueraDeRangoException;
use App\Validation\ValidadorFechas;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DomainException;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AnimalRepository::class)]
#[ORM\Table(name: 'animal')]
#[ORM\UniqueConstraint(name: 'uq_animal_codigo_interno', columns: ['codigo_interno'])]
#[ORM\UniqueConstraint(name: 'uq_animal_organizacion_arete', columns: ['id_organizacion', 'numero_arete'])]
#[ORM\UniqueConstraint(name: 'uq_animal_id_organizacion', columns: ['id_animal', 'id_organizacion'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['codigoInterno'], message: 'El código interno ya existe.')]
#[UniqueEntity(fields: ['organizacion', 'arete'], message: 'El arete ya existe en la organización.')]
class Animal implements AuditableInterface
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
    #[ORM\Column(name: 'id_animal', options: ['unsigned' => true])]
    private ?int $id = null;

    // Las FK compuestas con id_organizacion viven en el SQL inicial; Doctrine solo mapea cada columna.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_organizacion', referencedColumnName: 'id_organizacion', nullable: false, onDelete: 'RESTRICT')]
    private OrganizacionGanadera $organizacion;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_finca', referencedColumnName: 'id_finca', nullable: false, onDelete: 'RESTRICT')]
    private Finca $finca;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'raza_id', referencedColumnName: 'id_raza', nullable: true, onDelete: 'RESTRICT')]
    private ?Raza $raza = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'hijosComoPadre')]
    #[ORM\JoinColumn(name: 'id_padre', referencedColumnName: 'id_animal', nullable: true, onDelete: 'RESTRICT')]
    private ?self $padre = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'hijosComoMadre')]
    #[ORM\JoinColumn(name: 'id_madre', referencedColumnName: 'id_animal', nullable: true, onDelete: 'RESTRICT')]
    private ?self $madre = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(mappedBy: 'padre', targetEntity: self::class)]
    private Collection $hijosComoPadre;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(mappedBy: 'madre', targetEntity: self::class)]
    private Collection $hijosComoMadre;

    #[ORM\Column(name: 'codigo_interno', length: 36)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 36)]
    private string $codigoInterno;

    #[ORM\Column(name: 'numero_arete', length: 50)]
    #[Assert\NotBlank(message: 'El arete es obligatorio.')]
    #[Assert\Length(max: 50)]
    private ?string $arete = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $nombre = null;

    #[ORM\Column(length: 6)]
    #[Assert\Choice(choices: [self::SEXO_HEMBRA, self::SEXO_MACHO])]
    private string $sexo;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $fechaNacimiento = null;

    /** RN-19: límite inferior de los registros operativos; en los nacidos en finca es la fecha de nacimiento. */
    #[ORM\Column(name: 'fecha_ingreso', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $fechaIngreso;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $pesoNacimientoKg = null;

    #[ORM\Column(length: 15)]
    #[Assert\Choice(choices: [self::ORIGEN_NACIDO_EN_FINCA, self::ORIGEN_EXTERNO])]
    private string $origen;

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200)]
    private ?string $procedencia = null;

    #[ORM\Column(name: 'es_productora', options: ['default' => false])]
    private bool $productoraLeche = false;

    #[ORM\Column(length: 10, options: ['default' => self::ESTADO_ACTIVO])]
    #[Assert\Choice(choices: [self::ESTADO_ACTIVO, self::ESTADO_VENDIDO, self::ESTADO_FALLECIDO, self::ESTADO_EGRESADO])]
    private string $estado = self::ESTADO_ACTIVO;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $fechaSalida = null;

    public function __construct(OrganizacionGanadera $organizacion, Finca $finca, string $codigoInterno, string $sexo, string $origen, ?DateTimeImmutable $fechaIngreso = null)
    {
        $codigoInterno = trim($codigoInterno);
        if ('' === $codigoInterno || strlen($codigoInterno) > 36) {
            throw new DomainException('El código interno del animal no es válido.');
        }
        if ($finca->getOrganizacion() !== $organizacion) {
            throw new DomainException('La finca debe pertenecer a la organización del animal.');
        }
        if (!$finca->isActivo()) {
            throw new DomainException('La finca está inactiva y no admite animales nuevos.');
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
        $this->fechaIngreso = $fechaIngreso ?? new DateTimeImmutable('today');
        ValidadorFechas::assertNoFutura($this->fechaIngreso, 'La fecha de ingreso');
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
    public function getFechaIngreso(): DateTimeImmutable { return $this->fechaIngreso; }
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
        if ($finca !== $this->finca && !$finca->isActivo()) {
            throw new DomainException('La finca está inactiva y no admite animales nuevos.');
        }
        $this->finca = $finca;
        return $this;
    }

    public function setRaza(?Raza $raza): static
    {
        if (null !== $raza && $raza->getOrganizacion() !== $this->organizacion) {
            throw new DomainException('La raza debe pertenecer a la organización del animal.');
        }
        if (null !== $raza && $raza !== $this->raza && !$raza->isActiva()) {
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
        return $this->definirFechas($fecha, $this->fechaIngreso);
    }

    public function setFechaIngreso(DateTimeImmutable $fecha): static
    {
        return $this->definirFechas($this->fechaNacimiento, $fecha);
    }

    /**
     * Fija juntas las fechas de nacimiento e ingreso (RN-19): ninguna es futura, el nacimiento no es
     * posterior al ingreso y ninguna es posterior a la salida. En un nacido en finca, el ingreso es el nacimiento.
     */
    public function definirFechas(?DateTimeImmutable $nacimiento, ?DateTimeImmutable $ingreso): static
    {
        if (self::ORIGEN_NACIDO_EN_FINCA === $this->origen && null !== $nacimiento) {
            $ingreso = $nacimiento;
        }
        if (null === $ingreso) {
            throw new FechaFueraDeRangoException('Indica la fecha de ingreso.', 'fechaIngreso');
        }
        if (null !== $nacimiento) {
            ValidadorFechas::assertNoFutura($nacimiento, 'La fecha de nacimiento');
            if ($nacimiento->format('Y-m-d') > $ingreso->format('Y-m-d')) {
                throw new FechaFueraDeRangoException(sprintf(
                    'La fecha de nacimiento no puede ser posterior a la fecha de ingreso (%s).',
                    $ingreso->format('d/m/Y'),
                ));
            }
        }
        try {
            ValidadorFechas::assertNoFutura($ingreso, 'La fecha de ingreso');
        } catch (FechaFueraDeRangoException $exception) {
            throw new FechaFueraDeRangoException($exception->getMessage(), 'fechaIngreso');
        }
        if (null !== $this->fechaSalida) {
            if (null !== $nacimiento && $nacimiento > $this->fechaSalida) {
                throw new FechaFueraDeRangoException('La fecha de nacimiento no puede ser posterior a la salida.');
            }
            if ($ingreso > $this->fechaSalida) {
                throw new FechaFueraDeRangoException('La fecha de ingreso no puede ser posterior a la salida.', 'fechaIngreso');
            }
        }
        $this->fechaNacimiento = $nacimiento;
        $this->fechaIngreso = $ingreso;

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
        if (self::ORIGEN_NACIDO_EN_FINCA === $origen && null !== $this->fechaNacimiento) {
            $this->fechaIngreso = $this->fechaNacimiento;
        }

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

    /**
     * Cambia el estado a VENDIDO, FALLECIDO o EGRESADO con su fecha de salida (CA-04-C, RN-12).
     * La salida no puede ser futura, anterior al nacimiento, anterior al ingreso (RN-19) ni anterior al último registro operativo.
     */
    public function registrarSalida(string $estado, DateTimeImmutable $fecha, ?DateTimeImmutable $ultimoRegistro = null): static
    {
        if (!in_array($estado, [self::ESTADO_VENDIDO, self::ESTADO_FALLECIDO, self::ESTADO_EGRESADO], true)) {
            throw new DomainException('El estado de salida no es válido.');
        }
        ValidadorFechas::assertNoFutura($fecha, 'La fecha de salida');
        if (null !== $this->fechaNacimiento && $fecha < $this->fechaNacimiento) {
            throw new DomainException('La salida no puede preceder al nacimiento.');
        }
        if ($fecha->format('Y-m-d') < $this->fechaIngreso->format('Y-m-d')) {
            throw new DomainException(sprintf('La salida no puede ser anterior al ingreso del animal (%s).', $this->fechaIngreso->format('d/m/Y')));
        }
        if (null !== $ultimoRegistro && $fecha->format('Y-m-d') < $ultimoRegistro->format('Y-m-d')) {
            throw new DomainException(sprintf(
                'La salida no puede ser anterior al último registro operativo (%s).',
                $ultimoRegistro->format('d/m/Y'),
            ));
        }
        $this->estado = $estado;
        $this->fechaSalida = $fecha;
        return $this;
    }

    /** Devuelve el animal a ACTIVO; el estado activo no lleva fecha de salida. */
    public function reactivar(): static
    {
        $this->estado = self::ESTADO_ACTIVO;
        $this->fechaSalida = null;
        return $this;
    }

    public function isActivo(): bool
    {
        return self::ESTADO_ACTIVO === $this->estado;
    }

    /** Una finca inactiva conserva su historial, pero no recibe registros operativos nuevos. */
    public function assertFincaAdmiteRegistros(): void
    {
        if (!$this->finca->isActivo()) {
            throw new DomainException('La finca del animal está inactiva y no admite registros nuevos.');
        }
    }

    public function assertFechaOperativa(DateTimeInterface $fecha): void
    {
        ValidadorFechas::assertFechaOperativa($this, $fecha);
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
