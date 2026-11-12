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
        return $this->fechaExtrema($animal, 'MAX');
    }

    /** Fecha del registro operativo más antiguo; el nacimiento no puede ser posterior a ella (RN-11). */
    public function primeraFechaOperativa(Animal $animal): ?DateTimeImmutable
    {
        return $this->fechaExtrema($animal, 'MIN');
    }

    /** @param 'MIN'|'MAX' $funcion */
    private function fechaExtrema(Animal $animal, string $funcion): ?DateTimeImmutable
    {
        if (null === $animal->getId()) {
            return null;
        }

        $fecha = $this->connection->fetchOne(
            sprintf('SELECT %s(fecha) FROM (
                SELECT fecha_pesaje AS fecha FROM pesaje WHERE id_animal = :id
                UNION ALL SELECT fecha_vacunacion FROM vacunacion WHERE id_animal = :id
                UNION ALL SELECT fecha_monta FROM monta_reproductiva WHERE id_hembra = :id OR id_macho = :id
                UNION ALL SELECT DATE(fecha_hora) FROM produccion_leche WHERE id_animal = :id
            ) AS registros', $funcion),
            ['id' => $animal->getId()],
        );

        return false === $fecha || null === $fecha ? null : new DateTimeImmutable((string) $fecha);
    }

    public function tieneRegistrosOperativos(Animal $animal): bool
    {
        return null !== $this->ultimaFechaOperativa($animal);
    }

    /**
     * Registros que dependen del sexo actual del animal: montas como hembra y ordeños si es hembra,
     * montas como macho si es macho. Mientras existan, su sexo no puede cambiarse.
     */
    public function tieneRegistrosQueDependenDelSexo(Animal $animal): bool
    {
        if (null === $animal->getId()) {
            return false;
        }

        $sql = Animal::SEXO_HEMBRA === $animal->getSexo()
            ? 'SELECT EXISTS (SELECT 1 FROM monta_reproductiva WHERE id_hembra = :id)
                   OR EXISTS (SELECT 1 FROM produccion_leche WHERE id_animal = :id)'
            : 'SELECT EXISTS (SELECT 1 FROM monta_reproductiva WHERE id_macho = :id)';

        return (bool) $this->connection->fetchOne($sql, ['id' => $animal->getId()]);
    }

    public function tieneDescendientes(Animal $animal): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT EXISTS (SELECT 1 FROM animal WHERE id_madre = :id OR id_padre = :id)',
            ['id' => $animal->getId()],
        );
    }
}
