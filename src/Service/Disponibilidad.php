<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Calcula la disponibilidad mensual de RNF-P-08 a partir del registro del monitor de Compose.
 *
 * Cada línea del registro es "AAAA-MM-DDTHH:MM:SS estado", con estado "ok" o "caida", en hora de Guatemala.
 * Se evalúa cada minuto de la jornada (05:00 a 19:59): un minuto es disponible si tiene al menos una muestra
 * "ok". Un minuto sin muestras cuenta como no disponible, porque si se detiene todo el equipo el monitor
 * tampoco puede escribir.
 */
final class Disponibilidad
{
    public const HORA_INICIO = 5;
    public const HORA_FIN = 20;
    public const OBJETIVO = 98.0;

    /**
     * @param iterable<string> $lineas
     *
     * @return array{desde: ?string, minutos: int, disponibles: int, porcentaje: ?float, cumple: ?bool, caidas: list<array{desde: string, hasta: string, minutos: int}>}
     */
    public function calcular(iterable $lineas, string $mes, DateTimeImmutable $ahora): array
    {
        $zona = new DateTimeZone('America/Guatemala');
        $ok = [];
        $primeraMuestra = null;
        foreach ($lineas as $linea) {
            if (!preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}):\d{2}\s+(ok|caida)\s*$/', $linea, $m)) {
                continue;
            }
            $primeraMuestra ??= $m[1];
            if ('ok' === $m[2]) {
                $ok[$m[1]] = true;
            }
        }

        $inicioMes = new DateTimeImmutable($mes.'-01 00:00', $zona);
        $finMes = $inicioMes->modify('first day of next month');
        $limite = min($finMes, $ahora->setTimezone($zona));
        // El periodo empieza cuando el monitor empezó a registrar: antes no hay medición, no hay caída.
        $inicio = null === $primeraMuestra ? $limite : max($inicioMes, new DateTimeImmutable($primeraMuestra, $zona));

        $minutos = 0;
        $disponibles = 0;
        $caidas = [];
        $caidaActual = null;
        for ($minuto = $inicio; $minuto < $limite; $minuto = $minuto->modify('+1 minute')) {
            $hora = (int) $minuto->format('G');
            if ($hora < self::HORA_INICIO || $hora >= self::HORA_FIN) {
                $caidaActual = $this->cerrarCaida($caidaActual, $caidas);
                continue;
            }

            ++$minutos;
            if (isset($ok[$minuto->format('Y-m-d\TH:i')])) {
                ++$disponibles;
                $caidaActual = $this->cerrarCaida($caidaActual, $caidas);
            } else {
                $caidaActual ??= ['desde' => $minuto, 'hasta' => $minuto, 'minutos' => 0];
                $caidaActual['hasta'] = $minuto;
                ++$caidaActual['minutos'];
            }
        }
        $this->cerrarCaida($caidaActual, $caidas);

        $porcentaje = $minutos > 0 ? round($disponibles * 100 / $minutos, 2) : null;

        return [
            'desde' => $inicio < $limite ? $inicio->format('Y-m-d H:i') : null,
            'minutos' => $minutos,
            'disponibles' => $disponibles,
            'porcentaje' => $porcentaje,
            'cumple' => null === $porcentaje ? null : $porcentaje >= self::OBJETIVO,
            'caidas' => $caidas,
        ];
    }

    /**
     * @param array{desde: DateTimeImmutable, hasta: DateTimeImmutable, minutos: int}|null $caida
     * @param list<array{desde: string, hasta: string, minutos: int}> $caidas
     */
    private function cerrarCaida(?array $caida, array &$caidas): null
    {
        if (null !== $caida) {
            $caidas[] = [
                'desde' => $caida['desde']->format('Y-m-d H:i'),
                'hasta' => $caida['hasta']->format('Y-m-d H:i'),
                'minutos' => $caida['minutos'],
            ];
        }

        return null;
    }
}
