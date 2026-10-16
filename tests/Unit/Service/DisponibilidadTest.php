<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Disponibilidad;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class DisponibilidadTest extends TestCase
{
    public function testAFullDayWithSamplesEveryMinuteIsFullyAvailable(): void
    {
        $resultado = $this->calcular($this->muestras('2026-10-01 05:00', 900), '2026-10-01 20:00');

        self::assertSame(900, $resultado['minutos']);
        self::assertSame(100.0, $resultado['porcentaje']);
        self::assertTrue($resultado['cumple']);
        self::assertSame([], $resultado['caidas']);
    }

    public function testMissingAndFailedMinutesCountAsUnavailableAndAreListed(): void
    {
        $lineas = $this->muestras('2026-10-01 05:00', 900);
        // 10:00 a 10:19 sin muestras y 10:20 a 10:29 con muestra "caida": 30 minutos no disponibles.
        $lineas = array_values(array_filter($lineas, static fn (string $l): bool => !preg_match('/T10:[0-2]\d/', $l)));
        for ($m = 20; $m < 30; ++$m) {
            $lineas[] = sprintf('2026-10-01T10:%02d:05 caida', $m);
        }

        $resultado = $this->calcular($lineas, '2026-10-01 20:00');

        self::assertSame(870, $resultado['disponibles']);
        self::assertSame(96.67, $resultado['porcentaje']);
        self::assertFalse($resultado['cumple']);
        self::assertSame([['desde' => '2026-10-01 10:00', 'hasta' => '2026-10-01 10:29', 'minutos' => 30]], $resultado['caidas']);
    }

    public function testOnlyTheWorkdayFrom5To20IsEvaluated(): void
    {
        $lineas = array_merge(
            $this->muestras('2026-10-01 05:00', 900),
            ['2026-10-01T04:59:00 caida', '2026-10-01T20:00:00 caida', '2026-10-01T23:30:00 caida'],
        );

        $resultado = $this->calcular($lineas, '2026-10-02 04:00');

        self::assertSame(900, $resultado['minutos']);
        self::assertSame(100.0, $resultado['porcentaje']);
    }

    public function testThePeriodStartsWithTheFirstSampleOfTheMonitor(): void
    {
        $resultado = $this->calcular($this->muestras('2026-10-15 10:00', 120), '2026-10-15 12:00');

        self::assertSame('2026-10-15 10:00', $resultado['desde']);
        self::assertSame(120, $resultado['minutos']);
        self::assertSame(100.0, $resultado['porcentaje']);
    }

    public function testWithoutSamplesThereIsNothingToEvaluate(): void
    {
        $resultado = $this->calcular([], '2026-10-15 12:00');

        self::assertNull($resultado['porcentaje']);
        self::assertNull($resultado['cumple']);
        self::assertSame(0, $resultado['minutos']);
    }

    /** @return list<string> */
    private function muestras(string $desde, int $minutos): array
    {
        $zona = new DateTimeZone('America/Guatemala');
        $inicio = new DateTimeImmutable($desde, $zona);

        return array_map(
            static fn (int $i): string => $inicio->modify(sprintf('+%d minutes', $i))->format('Y-m-d\TH:i:15').' ok',
            range(0, $minutos - 1),
        );
    }

    /** @param list<string> $lineas */
    private function calcular(array $lineas, string $ahora): array
    {
        return (new Disponibilidad())->calcular($lineas, '2026-10', new DateTimeImmutable($ahora, new DateTimeZone('America/Guatemala')));
    }
}
