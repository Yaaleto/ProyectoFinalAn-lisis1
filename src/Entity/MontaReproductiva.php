<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\Auditable;
use App\Entity\Concerns\AuditableInterface;
use App\Repository\MontaReproductivaRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

#[ORM\Entity(repositoryClass: MontaReproductivaRepository::class)]
#[ORM\Table(name: 'monta_reproductiva')]
#[ORM\Index(name: 'idx_monta_hembra_fecha', columns: ['id_hembra', 'fecha_monta'])]
#[ORM\HasLifecycleCallbacks]
class MontaReproductiva implements AuditableInterface
{
    use Auditable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_monta', options: ['unsigned' => true])]
    private ?int $id = null;

    // Las FK compuestas con organizacion_id hacia la hembra y el macho viven en el SQL inicial.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'organizacion_id', referencedColumnName: 'id_organizacion', nullable: false, onDelete: 'RESTRICT')]
    private OrganizacionGanadera $organizacion;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_hembra', referencedColumnName: 'id_animal', nullable: false, onDelete: 'RESTRICT')]
    private Animal $hembra;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_macho', referencedColumnName: 'id_animal', nullable: true, onDelete: 'RESTRICT')]
    private ?Animal $macho;

    #[ORM\Column(name: 'fecha_monta', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $fecha;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observacion = null;

    public function __construct(Animal $hembra, ?Animal $macho, DateTimeImmutable $fecha)
    {
        if (Animal::SEXO_HEMBRA !== $hembra->getSexo()) {
            throw new DomainException('La hembra de la monta debe tener sexo HEMBRA.');
        }
        $hembra->assertFincaAdmiteRegistros();
        $this->organizacion = $hembra->getOrganizacion();
        $this->hembra = $hembra;
        $this->asignar($macho, $fecha);
    }

    /** La modificación aplica las reglas del alta; la hembra de la monta no cambia (RN-10, CA-08-B a D). */
    public function modificar(?Animal $macho, DateTimeImmutable $fecha, ?string $observacion): static
    {
        $this->hembra->assertFincaAdmiteRegistros();
        $this->asignar($macho, $fecha);
        $this->observacion = $observacion;
        return $this;
    }

    /** El macho es opcional y puede ser de otra finca, pero debe ser macho y de la misma organización. */
    private function asignar(?Animal $macho, DateTimeImmutable $fecha): void
    {
        if (null !== $macho && Animal::SEXO_MACHO !== $macho->getSexo()) {
            throw new DomainException('El macho de la monta debe tener sexo MACHO.');
        }
        if (null !== $macho && $macho->getOrganizacion() !== $this->organizacion) {
            throw new DomainException('Los animales de la monta deben pertenecer a la misma organización.');
        }
        $this->hembra->assertFechaOperativa($fecha);
        $macho?->assertFechaOperativa($fecha);
        $this->macho = $macho;
        $this->fecha = $fecha;
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganizacion(): OrganizacionGanadera { return $this->organizacion; }
    public function getHembra(): Animal { return $this->hembra; }
    public function getMacho(): ?Animal { return $this->macho; }
    public function getFecha(): DateTimeImmutable { return $this->fecha; }
    public function getObservacion(): ?string { return $this->observacion; }
    public function setObservacion(?string $observacion): static { $this->observacion = $observacion; return $this; }
}
