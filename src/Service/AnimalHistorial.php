<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Animal;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Consultas sobre los registros operativos y los descendientes de un animal,
 * necesarias para fechar su salida (RN-12) y decidir si puede eliminarse (CA-04-D, CA-04-E).
 */
final class AnimalHistorial
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** Fecha del registro operativo más reciente: pesaje, vacunación, monta (como hembra o macho) u ordeño. */
    public function ultimaFechaOperativa(Animal $animal): ?DateTimeImmutable
    {
        $fecha = $this->connection->fetchOne(
            'SELECT MAX(fecha) FROM (
                SELECT fecha_pesaje AS fecha FROM pesaje WHERE id_animal = :id
                UNION ALL SELECT fecha_vacunacion FROM vacunacion WHERE id_animal = :id
                UNION ALL SELECT fecha_monta FROM monta_reproductiva WHERE id_hembra = :id OR id_macho = :id
                UNION ALL SELECT DATE(fecha_hora) FROM produccion_leche WHERE id_animal = :id
            ) AS registros',
            ['id' => $animal->getId()],
        );

        return false === $fecha || null === $fecha ? null : new DateTimeImmutable((string) $fecha);
    }

    public function tieneRegistrosOperativos(Animal $animal): bool
    {
        return null !== $this->ultimaFechaOperativa($animal);
    }

    public function tieneDescendientes(Animal $animal): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT EXISTS (SELECT 1 FROM animal WHERE id_madre = :id OR id_padre = :id)',
            ['id' => $animal->getId()],
        );
    }
}
