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
    public function testAnimalRejectsBlankInternalCode(): void
    {
        [$organizacion, $finca] = $this->tenant();
        $this->expectException(DomainException::class);

        new Animal($organizacion, $finca, '  ', Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01'));
    }

    public function testAnimalRejectsInternalCodeLongerThanStorageLimit(): void
    {
        [$organizacion, $finca] = $this->tenant();
        $this->expectException(DomainException::class);

        new Animal($organizacion, $finca, str_repeat('A', 37), Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01'));
    }

    /** @dataProvider invalidClassifications */
    public function testAnimalRejectsInvalidClassifications(string $sexo, string $origen): void
    {
        [$organizacion, $finca] = $this->tenant();
        $this->expectException(DomainException::class);

        new Animal($organizacion, $finca, 'AN-1', $sexo, $origen);
    }

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

    public function testMilkProducerCannotBeChangedToMale(): void
    {
        $this->expectException(DomainException::class);

        $this->animal(Animal::SEXO_HEMBRA)
            ->setProductoraLeche(true)
            ->setSexo(Animal::SEXO_MACHO);
    }

    public function testAnimalRejectsAFarmFromAnotherOrganization(): void
    {
        $organizacion = new OrganizacionGanadera('Norte', 'NORTE');
        $fincaAjena = (new Finca('Sur', 'SUR'))->setOrganizacion(new OrganizacionGanadera('Sur', 'SUR'));

        $this->expectException(DomainException::class);

        new Animal($organizacion, $fincaAjena, 'AN-1', Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01'));
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
        $animal = new Animal($organizacion, $finca, 'CRIA', Animal::SEXO_HEMBRA, Animal::ORIGEN_NACIDO_EN_FINCA, new DateTimeImmutable('2025-01-01'));
        $hembra = new Animal($organizacion, $finca, 'HEMBRA', Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01'));
        $this->expectException(DomainException::class);

        $animal->setPadre($hembra);
    }

    public function testMotherMustBeFemale(): void
    {
        [$organizacion, $finca] = $this->tenant();
        $cria = new Animal($organizacion, $finca, 'CRIA', Animal::SEXO_HEMBRA, Animal::ORIGEN_NACIDO_EN_FINCA, new DateTimeImmutable('2025-01-01'));
        $macho = new Animal($organizacion, $finca, 'MACHO', Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01'));

        $this->expectException(DomainException::class);
        $cria->setMadre($macho);
    }

    public function testParentRelationshipCannotCreateAnAncestryCycle(): void
    {
        [$organizacion, $finca] = $this->tenant();
        $abuelo = new Animal($organizacion, $finca, 'ABUELO', Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01'));
        $padre = (new Animal($organizacion, $finca, 'PADRE', Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01')))->setPadre($abuelo);
        $cria = (new Animal($organizacion, $finca, 'CRIA', Animal::SEXO_MACHO, Animal::ORIGEN_NACIDO_EN_FINCA, new DateTimeImmutable('2025-01-01')))->setPadre($padre);

        $this->expectException(DomainException::class);
        $abuelo->setPadre($cria);
    }

    public function testFatherCannotChangeSexUntilParentLinkIsRemoved(): void
    {
        [$organizacion, $finca] = $this->tenant();
        $padre = new Animal($organizacion, $finca, 'PADRE', Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01'));
        $cria = (new Animal($organizacion, $finca, 'CRIA', Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01')))->setPadre($padre);

        try {
            $padre->setSexo(Animal::SEXO_HEMBRA);
            self::fail('Se permitió cambiar el sexo de un padre vinculado.');
        } catch (DomainException) {
            self::assertSame(Animal::SEXO_MACHO, $padre->getSexo());
        }

        $cria->setPadre(null);
        $padre->setSexo(Animal::SEXO_HEMBRA);
        self::assertSame(Animal::SEXO_HEMBRA, $padre->getSexo());
    }

    public function testMotherCannotChangeSexWhileReferenced(): void
    {
        [$organizacion, $finca] = $this->tenant();
        $madre = new Animal($organizacion, $finca, 'MADRE', Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01'));
        (new Animal($organizacion, $finca, 'CRIA', Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01')))->setMadre($madre);

        $this->expectException(DomainException::class);
        $madre->setSexo(Animal::SEXO_MACHO);
    }

    /** @dataProvider nonPositiveValues */
    public function testWeightRejectsNonPositiveValues(string $peso): void
    {
        $this->expectException(DomainException::class);

        new Pesaje($this->animal(), new DateTimeImmutable('2026-09-17'), $peso);
    }

    public function testWeightRejectsADateBeforeBirth(): void
    {
        $animal = $this->animal()->definirFechas(new DateTimeImmutable('2026-01-10'), new DateTimeImmutable('2026-01-10'));
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
        $animal = $this->animal()->definirFechas(new DateTimeImmutable('2026-01-10'), new DateTimeImmutable('2026-01-10'));
        $onBirth = new Pesaje($animal, new DateTimeImmutable('2026-01-10'), '35.50');
        $animal->registrarSalida(Animal::ESTADO_VENDIDO, new DateTimeImmutable('2026-06-01'));
        $onExit = new Pesaje($animal, new DateTimeImmutable('2026-06-01'), '300.00');

        self::assertSame('2026-01-10', $onBirth->getFecha()->format('Y-m-d'));
        self::assertSame('2026-06-01', $onExit->getFecha()->format('Y-m-d'));
    }

    public function testAnimalRejectsFutureBirthDate(): void
    {
        $this->expectException(DomainException::class);

        $this->animal()->setFechaNacimiento(new DateTimeImmutable('tomorrow'));
    }

    public function testAnimalRejectsBirthAfterRegisteredExit(): void
    {
        $animal = $this->animal()->registrarSalida(Animal::ESTADO_VENDIDO, new DateTimeImmutable('yesterday'));
        $this->expectException(DomainException::class);

        $animal->setFechaNacimiento(new DateTimeImmutable('today'));
    }

    public function testAnimalRejectsFutureExitDate(): void
    {
        $this->expectException(DomainException::class);

        $this->animal()->registrarSalida(Animal::ESTADO_EGRESADO, new DateTimeImmutable('tomorrow'));
    }

    public function testParentMustBelongToTheSameOrganization(): void
    {
        [$organizacion, $finca] = $this->tenant();
        $cria = new Animal($organizacion, $finca, 'CRIA', Animal::SEXO_HEMBRA, Animal::ORIGEN_NACIDO_EN_FINCA, new DateTimeImmutable('2025-01-01'));
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

        new ProduccionLeche($animal, $animal->getFinca(), new DateTimeImmutable('2026-09-17 06:00:00'), $cantidad);
    }

    public function testMilkProductionAllowsMultipleRecordsOnTheSameDay(): void
    {
        $animal = $this->animal(Animal::SEXO_HEMBRA)->setProductoraLeche(true);
        $morning = new ProduccionLeche($animal, $animal->getFinca(), new DateTimeImmutable('2026-09-17 06:00:00'), '8.50');
        $evening = new ProduccionLeche($animal, $animal->getFinca(), new DateTimeImmutable('2026-09-17 17:00:00'), '7.25');

        self::assertSame('15.75', number_format((float) $morning->getCantidadLitros() + (float) $evening->getCantidadLitros(), 2, '.', ''));
    }

    public function testMilkProductionRequiresAFemaleProducer(): void
    {
        $animal = $this->animal(Animal::SEXO_HEMBRA);
        $this->expectException(DomainException::class);

        new ProduccionLeche($animal, $animal->getFinca(), new DateTimeImmutable('2026-09-17 06:00:00'), '8.50');
    }

    public function testMilkProductionKeepsTheConfirmedMilkingFarm(): void
    {
        $animal = $this->animal(Animal::SEXO_HEMBRA)->setProductoraLeche(true);
        $fincaAnterior = (new Finca('Finca anterior', 'ANT-'.bin2hex(random_bytes(3))))->setOrganizacion($animal->getOrganizacion());

        $ordeno = new ProduccionLeche($animal, $fincaAnterior, new DateTimeImmutable('2026-09-17 06:00:00'), '8.50');

        self::assertSame($fincaAnterior, $ordeno->getFincaOrdeno());
        self::assertSame($animal->getOrganizacion(), $ordeno->getOrganizacion());
    }

    public function testMilkProductionRejectsAFarmFromAnotherOrganization(): void
    {
        $animal = $this->animal(Animal::SEXO_HEMBRA)->setProductoraLeche(true);
        [, $fincaAjena] = $this->tenant();
        $this->expectException(DomainException::class);

        new ProduccionLeche($animal, $fincaAjena, new DateTimeImmutable('2026-09-17 06:00:00'), '8.50');
    }

    public function testAnimalCannotBeRegisteredOnAnInactiveFarm(): void
    {
        [$organizacion, $finca] = $this->tenant();
        $finca->setActivo(false);
        $this->expectException(DomainException::class);

        new Animal($organizacion, $finca, 'AN-INACTIVA', Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01'));
    }

    public function testAnimalCannotBeMovedToAnInactiveFarm(): void
    {
        $animal = $this->animal();
        $fincaInactiva = (new Finca('Inactiva', 'INA-'.bin2hex(random_bytes(3))))
            ->setOrganizacion($animal->getOrganizacion())
            ->setActivo(false);
        $this->expectException(DomainException::class);

        $animal->setFinca($fincaInactiva);
    }

    public function testAnimalKeepsItsFarmAfterTheFarmIsDeactivated(): void
    {
        $animal = $this->animal();
        $animal->getFinca()->setActivo(false);

        $animal->setFinca($animal->getFinca());

        self::assertFalse($animal->getFinca()->isActivo());
    }

    public function testOperationalRecordsAreRejectedOnAnInactiveFarm(): void
    {
        $hembra = $this->animal(Animal::SEXO_HEMBRA)->setProductoraLeche(true);
        $vacuna = new Vacuna($hembra->getOrganizacion(), 'Aftosa');
        $hembra->getFinca()->setActivo(false);
        $fecha = new DateTimeImmutable('2026-09-17');
        $intentos = [
            'pesaje' => static fn () => new Pesaje($hembra, $fecha, '120.00'),
            'vacunación' => static fn () => new Vacunacion($hembra, $vacuna, $fecha, '5 ml'),
            'monta' => static fn () => new MontaReproductiva($hembra, null, $fecha),
            'ordeño' => static fn () => new ProduccionLeche($hembra, $hembra->getFinca(), new DateTimeImmutable('2026-09-17 06:00:00'), '8.50'),
        ];

        foreach ($intentos as $registro => $crear) {
            try {
                $crear();
                self::fail(sprintf('Se aceptó un registro de %s en una finca inactiva.', $registro));
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function nonPositiveValues(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-0.01'];
        yield 'not numeric' => ['invalid'];
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidClassifications(): iterable
    {
        yield 'sex' => ['DESCONOCIDO', Animal::ORIGEN_EXTERNO];
        yield 'origin' => [Animal::SEXO_HEMBRA, 'DESCONOCIDO'];
    }

    private function animal(string $sexo = Animal::SEXO_HEMBRA): Animal
    {
        [$organizacion, $finca] = $this->tenant();

        return new Animal($organizacion, $finca, bin2hex(random_bytes(6)), $sexo, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01'));
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
