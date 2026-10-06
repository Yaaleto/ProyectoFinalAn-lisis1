<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validation;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Validation\ValidadorFechas;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ValidadorFechasTest extends TestCase
{
    public function testOperationalDateInsideTheAnimalLifeIsAccepted(): void
    {
        $animal = $this->animal('2026-01-10')->registrarSalida(Animal::ESTADO_VENDIDO, new DateTimeImmutable('2026-06-01'));

        ValidadorFechas::assertFechaOperativa($animal, new DateTimeImmutable('2026-03-15'));

        $this->addToAssertionCount(1);
    }

    public function testOperationalDateBeforeBirthIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('anterior al nacimiento');

        ValidadorFechas::assertFechaOperativa($this->animal('2026-01-10'), new DateTimeImmutable('2026-01-09'));
    }

    public function testOperationalDateAfterExitIsRejected(): void
    {
        $animal = $this->animal('2026-01-10')->registrarSalida(Animal::ESTADO_FALLECIDO, new DateTimeImmutable('2026-06-01'));
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('posterior a la salida');

        ValidadorFechas::assertFechaOperativa($animal, new DateTimeImmutable('2026-06-02'));
    }

    public function testARecordWithTimeOnTheExitDayIsAccepted(): void
    {
        $animal = $this->animal('2026-01-10')->registrarSalida(Animal::ESTADO_VENDIDO, new DateTimeImmutable('2026-06-01'));

        ValidadorFechas::assertFechaOperativa($animal, new DateTimeImmutable('2026-06-01 17:30:00'));

        $this->addToAssertionCount(1);
    }

    public function testFutureOperationalDateIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('futuro');

        ValidadorFechas::assertFechaOperativa($this->animal(), new DateTimeImmutable('tomorrow'));
    }

    public function testTodayIsAcceptedEvenLaterInTheDay(): void
    {
        ValidadorFechas::assertFechaOperativa($this->animal(), new DateTimeImmutable('today 23:59:00'));

        $this->addToAssertionCount(1);
    }

    public function testFutureBirthDateIsRejected(): void
    {
        $this->expectException(DomainException::class);

        $this->animal()->setFechaNacimiento(new DateTimeImmutable('tomorrow'));
    }

    public function testExitCannotPrecedeTheLastOperationalRecord(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('último registro operativo (20/05/2026)');

        $this->animal('2026-01-10')->registrarSalida(
            Animal::ESTADO_VENDIDO,
            new DateTimeImmutable('2026-05-19'),
            new DateTimeImmutable('2026-05-20'),
        );
    }

    public function testExitOnTheDayOfTheLastOperationalRecordIsAccepted(): void
    {
        $animal = $this->animal('2026-01-10')->registrarSalida(
            Animal::ESTADO_EGRESADO,
            new DateTimeImmutable('2026-05-20'),
            new DateTimeImmutable('2026-05-20'),
        );

        self::assertSame(Animal::ESTADO_EGRESADO, $animal->getEstado());
    }

    public function testReactivatingAnAnimalClearsItsExitDate(): void
    {
        $animal = $this->animal('2026-01-10')->registrarSalida(Animal::ESTADO_VENDIDO, new DateTimeImmutable('2026-06-01'));

        $animal->reactivar();

        self::assertSame(Animal::ESTADO_ACTIVO, $animal->getEstado());
        self::assertNull($animal->getFechaSalida());
        self::assertTrue($animal->isActivo());
    }

    private function animal(?string $nacimiento = null): Animal
    {
        $codigo = strtoupper(bin2hex(random_bytes(3)));
        $organizacion = new OrganizacionGanadera('Ganadería '.$codigo, $codigo);
        $finca = (new Finca('Finca '.$codigo, $codigo))->setOrganizacion($organizacion);
        $animal = new Animal($organizacion, $finca, 'AN-'.$codigo, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO);

        return null === $nacimiento ? $animal : $animal->setFechaNacimiento(new DateTimeImmutable($nacimiento));
    }
}
