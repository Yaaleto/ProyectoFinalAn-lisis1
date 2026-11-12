<?php

declare(strict_types=1);

namespace App\Validation;

use App\Entity\Animal;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Validador de fechas compartido (DERCAS v1.2, sección 5.2).
 * Compara solo la parte de fecha, de modo que un ordeño con hora cuenta en su día.
 */
final class ValidadorFechas
{
    /** RN-15: ninguna fecha puede ser posterior a la actual. */
    public static function assertNoFutura(DateTimeInterface $fecha, string $descripcion): void
    {
        if (self::dia($fecha) > self::dia(new DateTimeImmutable('today'))) {
            throw new FechaFueraDeRangoException(sprintf('%s no puede estar en el futuro.', $descripcion));
        }
    }

    /**
     * Un registro operativo no puede ser anterior al nacimiento (RN-11) ni al ingreso (RN-19),
     * posterior a la salida (RN-12) ni posterior a la fecha actual (RN-15).
     */
    public static function assertFechaOperativa(Animal $animal, DateTimeInterface $fecha): void
    {
        self::assertNoFutura($fecha, 'La fecha del registro');

        $nacimiento = $animal->getFechaNacimiento();
        if (null !== $nacimiento && self::dia($fecha) < self::dia($nacimiento)) {
            throw new FechaFueraDeRangoException('La fecha del registro no puede ser anterior al nacimiento del animal.');
        }

        $ingreso = $animal->getFechaIngreso();
        if (self::dia($fecha) < self::dia($ingreso)) {
            throw new FechaFueraDeRangoException(sprintf(
                'La fecha del registro no puede ser anterior al ingreso del animal (%s).',
                $ingreso->format('d/m/Y'),
            ));
        }

        $salida = $animal->getFechaSalida();
        if (null !== $salida && self::dia($fecha) > self::dia($salida)) {
            throw new FechaFueraDeRangoException('La fecha del registro no puede ser posterior a la salida del animal.');
        }
    }

    private static function dia(DateTimeInterface $fecha): string
    {
        return $fecha->format('Y-m-d');
    }
}
