<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validation;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Validation\FechaFueraDeRangoException;
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

    public function testOperationalDateBeforeEntryIsRejectedEvenWithoutBirthDate(): void
    {
        $animal = $this->animalExterno('2026-03-01');
        ValidadorFechas::assertFechaOperativa($animal, new DateTimeImmutable('2026-03-01 06:00:00'));

        $this->expectException(FechaFueraDeRangoException::class);
        $this->expectExceptionMessage('La fecha del registro no puede ser anterior al ingreso del animal (01/03/2026).');

        ValidadorFechas::assertFechaOperativa($animal, new DateTimeImmutable('2026-02-28 23:00:00'));
    }

    public function testBirthDateCannotBeAfterTheEntryDate(): void
    {
        $animal = $this->animalExterno('2026-03-01')->setFechaNacimiento(new DateTimeImmutable('2025-11-20'));
        self::assertSame('2026-03-01', $animal->getFechaIngreso()->format('Y-m-d'));

        try {
            $animal->setFechaNacimiento(new DateTimeImmutable('2026-03-02'));
            self::fail('Se esperaba un nacimiento posterior al ingreso rechazado.');
        } catch (FechaFueraDeRangoException $exception) {
            self::assertSame('La fecha de nacimiento no puede ser posterior a la fecha de ingreso (01/03/2026).', $exception->getMessage());
            self::assertNull($exception->campo);
        }

        $this->expectException(FechaFueraDeRangoException::class);
        $animal->setFechaIngreso(new DateTimeImmutable('2025-11-19'));
    }

    public function testEntryDateCannotBeInTheFuture(): void
    {
        try {
            $this->animalExterno('2026-03-01')->setFechaIngreso(new DateTimeImmutable('tomorrow'));
            self::fail('Se esperaba una fecha de ingreso futura rechazada.');
        } catch (FechaFueraDeRangoException $exception) {
            self::assertSame('La fecha de ingreso no puede estar en el futuro.', $exception->getMessage());
            self::assertSame('fechaIngreso', $exception->campo);
        }
    }

    public function testAnAnimalBornOnTheFarmEntersOnItsBirthDate(): void
    {
        $codigo = strtoupper(bin2hex(random_bytes(3)));
        $organizacion = new OrganizacionGanadera('Ganadería '.$codigo, $codigo);
        $finca = (new Finca('Finca '.$codigo, $codigo))->setOrganizacion($organizacion);
        $cria = (new Animal($organizacion, $finca, 'AN-'.$codigo, Animal::SEXO_MACHO, Animal::ORIGEN_NACIDO_EN_FINCA))
            ->setFechaNacimiento(new DateTimeImmutable('2026-02-10'));

        self::assertSame('2026-02-10', $cria->getFechaIngreso()->format('Y-m-d'));
    }

    public function testExitCannotPrecedeTheEntryDate(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La salida no puede ser anterior al ingreso del animal (01/03/2026).');

        $this->animalExterno('2026-03-01')->registrarSalida(Animal::ESTADO_VENDIDO, new DateTimeImmutable('2026-02-28'));
    }

    private function animalExterno(string $ingreso): Animal
    {
        $codigo = strtoupper(bin2hex(random_bytes(3)));
        $organizacion = new OrganizacionGanadera('Ganadería '.$codigo, $codigo);
        $finca = (new Finca('Finca '.$codigo, $codigo))->setOrganizacion($organizacion);

        return new Animal($organizacion, $finca, 'AN-'.$codigo, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable($ingreso));
    }

    private function animal(?string $nacimiento = null): Animal
    {
        $codigo = strtoupper(bin2hex(random_bytes(3)));
        $organizacion = new OrganizacionGanadera('Ganadería '.$codigo, $codigo);
        $finca = (new Finca('Finca '.$codigo, $codigo))->setOrganizacion($organizacion);
        $animal = new Animal($organizacion, $finca, 'AN-'.$codigo, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01'));

        return null === $nacimiento ? $animal : $animal->definirFechas(new DateTimeImmutable($nacimiento), new DateTimeImmutable($nacimiento));
    }
}
