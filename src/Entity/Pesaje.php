<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\Auditable;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

#[ORM\Entity]
#[ORM\Table(name: 'pesaje')]
#[ORM\UniqueConstraint(name: 'uq_pesaje_animal_fecha', columns: ['id_animal', 'fecha_pesaje'])]
#[ORM\HasLifecycleCallbacks]
class Pesaje
{
    use Auditable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_pesaje', options: ['unsigned' => true])]
    private ?int $id = null;

    // La FK compuesta (id_animal, organizacion_id) vive en el SQL inicial.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'organizacion_id', referencedColumnName: 'id_organizacion', nullable: false, onDelete: 'RESTRICT')]
    private OrganizacionGanadera $organizacion;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_animal', referencedColumnName: 'id_animal', nullable: false, onDelete: 'RESTRICT')]
    private Animal $animal;

    #[ORM\Column(name: 'fecha_pesaje', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $fecha;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $pesoKg;

    public function __construct(Animal $animal, DateTimeImmutable $fecha, string $pesoKg)
    {
        $animal->assertFechaOperativa($fecha);
        $this->assertPeso($pesoKg);
        $this->organizacion = $animal->getOrganizacion();
        $this->animal = $animal;
        $this->fecha = $fecha;
        $this->pesoKg = $pesoKg;
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganizacion(): OrganizacionGanadera { return $this->organizacion; }
    public function getAnimal(): Animal { return $this->animal; }
    public function getFecha(): DateTimeImmutable { return $this->fecha; }
    public function getPesoKg(): string { return $this->pesoKg; }

    public function setPesoKg(string $pesoKg): static
    {
        $this->assertPeso($pesoKg);
        $this->pesoKg = $pesoKg;
        return $this;
    }

    private function assertPeso(string $pesoKg): void
    {
        if (!is_numeric($pesoKg) || (float) $pesoKg <= 0) {
            throw new DomainException('El peso debe ser mayor que cero.');
        }
    }
}
