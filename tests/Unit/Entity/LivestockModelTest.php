<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\MontaReproductiva;
use App\Entity\OrganizacionGanadera;
use App\Entity\Pesaje;
use App\Entity\ProduccionLeche;
use App\Entity\Raza;
use App\Entity\Vacuna;
use App\Entity\Vacunacion;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class LivestockModelTest extends TestCase
{
    public function testAnimalAcceptsAValidFemaleMilkProducer(): void
    {
        $animal = $this->animal(Animal::SEXO_HEMBRA);

        $animal->setProductoraLeche(true);

        self::assertTrue($animal->isProductoraLeche());
    }

    public function testMaleCannotBeMarkedAsMilkProducer(): void
    {
        $this->expectException(DomainException::class);

        $this->animal(Animal::SEXO_MACHO)->setProductoraLeche(true);
    }

    public function testAnimalRejectsAFarmFromAnotherOrganization(): void
    {
        $organizacion = new OrganizacionGanadera('Norte', 'NORTE');
        $fincaAjena = (new Finca('Sur', 'SUR'))->setOrganizacion(new OrganizacionGanadera('Sur', 'SUR'));

        $this->expectException(DomainException::class);

        new Animal($organizacion, $fincaAjena, 'AN-1', Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO);
    }

    public function testAnimalCannotBeItsOwnParent(): void
    {
        $animal = $this->animal(Animal::SEXO_MACHO);
        $this->expectException(DomainException::class);

        $animal->setPadre($animal);
    }

    public function testFatherMustBeMale(): void
    {
        [$organizacion, $finca] = $this->tenant();
        $animal = new Animal($organizacion, $finca, 'CRIA', Animal::SEXO_HEMBRA, Animal::ORIGEN_NACIDO_EN_FINCA);
        $hembra = new Animal($organizacion, $finca, 'HEMBRA', Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO);
        $this->expectException(DomainException::class);

        $animal->setPadre($hembra);
    }

    /** @dataProvider nonPositiveValues */
    public function testWeightRejectsNonPositiveValues(string $peso): void
    {
        $this->expectException(DomainException::class);

        new Pesaje($this->animal(), new DateTimeImmutable('2026-09-17'), $peso);
    }

    public function testWeightRejectsADateBeforeBirth(): void
    {
        $animal = $this->animal()->setFechaNacimiento(new DateTimeImmutable('2026-01-10'));
        $this->expectException(DomainException::class);

        new Pesaje($animal, new DateTimeImmutable('2026-01-09'), '35.50');
    }

    public function testOperationalRecordRejectsADateAfterExit(): void
    {
        $animal = $this->animal()->registrarSalida(Animal::ESTADO_VENDIDO, new DateTimeImmutable('2026-06-01'));
        $this->expectException(DomainException::class);

        new Pesaje($animal, new DateTimeImmutable('2026-06-02'), '300');
    }

    public function testOperationalRecordAllowsBirthAndExitDates(): void
    {
        $animal = $this->animal()->setFechaNacimiento(new DateTimeImmutable('2026-01-10'));
        $onBirth = new Pesaje($animal, new DateTimeImmutable('2026-01-10'), '35.50');
        $animal->registrarSalida(Animal::ESTADO_VENDIDO, new DateTimeImmutable('2026-06-01'));
        $onExit = new Pesaje($animal, new DateTimeImmutable('2026-06-01'), '300.00');

        self::assertSame('2026-01-10', $onBirth->getFecha()->format('Y-m-d'));
        self::assertSame('2026-06-01', $onExit->getFecha()->format('Y-m-d'));
    }

    public function testParentMustBelongToTheSameOrganization(): void
    {
        [$organizacion, $finca] = $this->tenant();
        $cria = new Animal($organizacion, $finca, 'CRIA', Animal::SEXO_HEMBRA, Animal::ORIGEN_NACIDO_EN_FINCA);
        $otraOrganizacion = new OrganizacionGanadera('Otra organización', 'OTRA');
        $padreAjeno = new Animal(
            $otraOrganizacion,
            (new Finca('Finca ajena', 'AJENA'))->setOrganizacion($otraOrganizacion),
            'PADRE-AJENO',
            Animal::SEXO_MACHO,
            Animal::ORIGEN_EXTERNO,
        );

        $this->expectException(DomainException::class);

        $cria->setPadre($padreAjeno);
    }

    public function testVaccinationRejectsInactiveVaccine(): void
    {
        $animal = $this->animal();
        $vacuna = (new Vacuna($animal->getOrganizacion(), 'Clostridial'))->setActiva(false);
        $this->expectException(DomainException::class);

        new Vacunacion($animal, $vacuna, new DateTimeImmutable('2026-09-17'), '5 ml');
    }

    public function testAnimalRejectsInactiveBreed(): void
    {
        $animal = $this->animal();
        $raza = (new Raza($animal->getOrganizacion(), 'Brahman'))->setActiva(false);

        $this->expectException(DomainException::class);

        $animal->setRaza($raza);
    }

    public function testVaccinationRejectsForeignVaccine(): void
    {
        $animal = $this->animal();
        $vacuna = new Vacuna(new OrganizacionGanadera('Sur', 'SUR'), 'Clostridial');
        $this->expectException(DomainException::class);

        new Vacunacion($animal, $vacuna, new DateTimeImmutable('2026-09-17'), '5 ml');
    }

    public function testMatingAcceptsFemaleWithoutKnownMale(): void
    {
        $monta = new MontaReproductiva($this->animal(Animal::SEXO_HEMBRA), null, new DateTimeImmutable('2026-09-17'));

        self::assertNull($monta->getMacho());
    }

    public function testMatingRejectsMaleInFemaleRole(): void
    {
        $this->expectException(DomainException::class);

        new MontaReproductiva($this->animal(Animal::SEXO_MACHO), null, new DateTimeImmutable('2026-09-17'));
    }

    public function testMatingRejectsAnimalsFromDifferentOrganizations(): void
    {
        $this->expectException(DomainException::class);

        new MontaReproductiva($this->animal(Animal::SEXO_HEMBRA), $this->animal(Animal::SEXO_MACHO), new DateTimeImmutable('2026-09-17'));
    }

    /** @dataProvider nonPositiveValues */
    public function testMilkProductionRejectsNonPositiveValues(string $cantidad): void
    {
        $animal = $this->animal(Animal::SEXO_HEMBRA)->setProductoraLeche(true);
        $this->expectException(DomainException::class);

        new ProduccionLeche($animal, new DateTimeImmutable('2026-09-17 06:00:00'), $cantidad);
    }

    public function testMilkProductionAllowsMultipleRecordsOnTheSameDay(): void
    {
        $animal = $this->animal(Animal::SEXO_HEMBRA)->setProductoraLeche(true);
        $morning = new ProduccionLeche($animal, new DateTimeImmutable('2026-09-17 06:00:00'), '8.50');
        $evening = new ProduccionLeche($animal, new DateTimeImmutable('2026-09-17 17:00:00'), '7.25');

        self::assertSame('15.75', number_format((float) $morning->getCantidadLitros() + (float) $evening->getCantidadLitros(), 2, '.', ''));
    }

    public function testMilkProductionRequiresAFemaleProducer(): void
    {
        $this->expectException(DomainException::class);

        new ProduccionLeche($this->animal(Animal::SEXO_HEMBRA), new DateTimeImmutable('2026-09-17 06:00:00'), '8.50');
    }

    /** @return iterable<string, array{string}> */
    public static function nonPositiveValues(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-0.01'];
        yield 'not numeric' => ['invalid'];
    }

    private function animal(string $sexo = Animal::SEXO_HEMBRA): Animal
    {
        [$organizacion, $finca] = $this->tenant();

        return new Animal($organizacion, $finca, bin2hex(random_bytes(6)), $sexo, Animal::ORIGEN_EXTERNO);
    }

    /** @return array{OrganizacionGanadera, Finca} */
    private function tenant(): array
    {
        $codigo = strtoupper(bin2hex(random_bytes(3)));
        $organizacion = new OrganizacionGanadera('Ganadería '.$codigo, $codigo);
        $finca = (new Finca('Finca '.$codigo, $codigo))->setOrganizacion($organizacion);

        return [$organizacion, $finca];
    }
}
