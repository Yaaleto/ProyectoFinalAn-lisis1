<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\Auditable;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

#[ORM\Entity]
#[ORM\Table(name: 'produccion_leche')]
#[ORM\Index(name: 'idx_produccion_animal_fecha', columns: ['id_animal', 'fecha_hora'])]
#[ORM\HasLifecycleCallbacks]
class ProduccionLeche
{
    use Auditable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_produccion', options: ['unsigned' => true])]
    private ?int $id = null;

    // Las FK compuestas con organizacion_id hacia animal y finca viven en el SQL inicial.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'organizacion_id', referencedColumnName: 'id_organizacion', nullable: false, onDelete: 'RESTRICT')]
    private OrganizacionGanadera $organizacion;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_animal', referencedColumnName: 'id_animal', nullable: false, onDelete: 'RESTRICT')]
    private Animal $animal;

    // Finca donde ocurrió el ordeño; no se deduce de la finca actual del animal.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'id_finca_ordeno', referencedColumnName: 'id_finca', nullable: false, onDelete: 'RESTRICT')]
    private Finca $fincaOrdeno;

    #[ORM\Column(name: 'fecha_hora', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $fechaHora;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $cantidadLitros;

    public function __construct(Animal $animal, Finca $fincaOrdeno, DateTimeImmutable $fechaHora, string $cantidadLitros)
    {
        if (Animal::SEXO_HEMBRA !== $animal->getSexo() || !$animal->isProductoraLeche()) {
            throw new DomainException('La producción requiere una hembra productora de leche.');
        }
        if ($fincaOrdeno->getOrganizacion() !== $animal->getOrganizacion()) {
            throw new DomainException('La finca del ordeño debe pertenecer a la organización del animal.');
        }
        if (!is_numeric($cantidadLitros) || (float) $cantidadLitros <= 0) {
            throw new DomainException('La cantidad de leche debe ser mayor que cero.');
        }
        $animal->assertFechaOperativa($fechaHora);
        $this->organizacion = $animal->getOrganizacion();
        $this->animal = $animal;
        $this->fincaOrdeno = $fincaOrdeno;
        $this->fechaHora = $fechaHora;
        $this->cantidadLitros = $cantidadLitros;
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganizacion(): OrganizacionGanadera { return $this->organizacion; }
    public function getAnimal(): Animal { return $this->animal; }
    public function getFincaOrdeno(): Finca { return $this->fincaOrdeno; }
    public function getFechaHora(): DateTimeImmutable { return $this->fechaHora; }
    public function getCantidadLitros(): string { return $this->cantidadLitros; }
}
