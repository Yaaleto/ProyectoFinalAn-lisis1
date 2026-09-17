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
#[ORM\Index(name: 'idx_leche_animal_fecha_hora', columns: ['animal_id', 'fecha_hora'])]
#[ORM\HasLifecycleCallbacks]
class ProduccionLeche
{
    use Auditable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Animal $animal;

    #[ORM\Column(name: 'fecha_hora', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $fechaHora;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $cantidadLitros;

    public function __construct(Animal $animal, DateTimeImmutable $fechaHora, string $cantidadLitros)
    {
        if (Animal::SEXO_HEMBRA !== $animal->getSexo() || !$animal->isProductoraLeche()) {
            throw new DomainException('La producción requiere una hembra productora de leche.');
        }
        if (!is_numeric($cantidadLitros) || (float) $cantidadLitros <= 0) {
            throw new DomainException('La cantidad de leche debe ser mayor que cero.');
        }
        $animal->assertFechaOperativa($fechaHora);
        $this->animal = $animal;
        $this->fechaHora = $fechaHora;
        $this->cantidadLitros = $cantidadLitros;
    }

    public function getId(): ?int { return $this->id; }
    public function getAnimal(): Animal { return $this->animal; }
    public function getFechaHora(): DateTimeImmutable { return $this->fechaHora; }
    public function getCantidadLitros(): string { return $this->cantidadLitros; }
}
