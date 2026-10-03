<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\Auditable;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

#[ORM\Entity]
#[ORM\Table(name: 'vacunacion')]
#[ORM\Index(name: 'idx_vacunacion_animal_fecha', columns: ['id_animal', 'fecha_vacunacion'])]
#[ORM\HasLifecycleCallbacks]
class Vacunacion
{
    use Auditable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_vacunacion', options: ['unsigned' => true])]
    private ?int $id = null;

    // Las FK compuestas con organizacion_id hacia animal y vacuna viven en el SQL inicial.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'organizacion_id', referencedColumnName: 'id_organizacion', nullable: false, onDelete: 'RESTRICT')]
    private OrganizacionGanadera $organizacion;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_animal', referencedColumnName: 'id_animal', nullable: false, onDelete: 'RESTRICT')]
    private Animal $animal;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_vacuna', referencedColumnName: 'id_vacuna', nullable: false, onDelete: 'RESTRICT')]
    private Vacuna $vacuna;

    #[ORM\Column(name: 'fecha_vacunacion', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $fecha;

    #[ORM\Column(length: 50)]
    private string $dosis;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observaciones = null;

    public function __construct(Animal $animal, Vacuna $vacuna, DateTimeImmutable $fecha, string $dosis)
    {
        $animal->assertFechaOperativa($fecha);
        if ($vacuna->getOrganizacion() !== $animal->getOrganizacion()) {
            throw new DomainException('La vacuna debe pertenecer a la organización del animal.');
        }
        if (!$vacuna->isActiva()) {
            throw new DomainException('La vacuna debe estar activa.');
        }
        if ('' === trim($dosis)) {
            throw new DomainException('La dosis es obligatoria.');
        }
        $this->organizacion = $animal->getOrganizacion();
        $this->animal = $animal;
        $this->vacuna = $vacuna;
        $this->fecha = $fecha;
        $this->dosis = $dosis;
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganizacion(): OrganizacionGanadera { return $this->organizacion; }
    public function getAnimal(): Animal { return $this->animal; }
    public function getVacuna(): Vacuna { return $this->vacuna; }
    public function getFecha(): DateTimeImmutable { return $this->fecha; }
    public function getDosis(): string { return $this->dosis; }
    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $observaciones): static { $this->observaciones = $observaciones; return $this; }
}
