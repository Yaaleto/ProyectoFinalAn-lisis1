<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Pesaje;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseSchemaTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        parent::tearDown();
    }

    public function testMvpTablesExistInTheTestDatabase(): void
    {
        $tables = $this->entityManager->getConnection()->createSchemaManager()->listTableNames();

        foreach (['organizacion_ganadera', 'usuario', 'finca', 'raza', 'vacuna', 'animal', 'pesaje', 'vacunacion', 'monta_reproductiva', 'produccion_leche'] as $table) {
            self::assertContains($table, $tables);
        }
    }

    public function testDatabaseRejectsTwoWeightsForTheSameAnimalAndDate(): void
    {
        [$animal] = $this->persistAnimal('PESAJE');
        $fecha = new \DateTimeImmutable('2026-09-17');
        $this->entityManager->persist(new Pesaje($animal, $fecha, '120.00'));
        $this->entityManager->persist(new Pesaje($animal, $fecha, '121.00'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testDatabaseRejectsDuplicateFarmCodeInsideOneOrganization(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $primera = (new Finca('Finca uno', 'DUP-'.$suffix))->setOrganizacion($organizacion);
        $segunda = (new Finca('Finca dos', 'DUP-'.$suffix))->setOrganizacion($organizacion);
        foreach ([$organizacion, $primera, $segunda] as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testSameEarringIsAllowedInDifferentOrganizations(): void
    {
        [$primero] = $this->persistAnimal('ARETE-A', 'GT-101');
        [$segundo] = $this->persistAnimal('ARETE-B', 'GT-101');

        $this->entityManager->flush();

        self::assertNotNull($primero->getId());
        self::assertNotNull($segundo->getId());
    }

    public function testDatabaseRejectsDuplicateEarringInsideOneOrganization(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $finca = (new Finca('Finca '.$suffix, 'FIN-'.$suffix))->setOrganizacion($organizacion);
        $primero = (new Animal($organizacion, $finca, 'A-'.$suffix, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO))->setArete('GT-101');
        $segundo = (new Animal($organizacion, $finca, 'B-'.$suffix, Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO))->setArete('GT-101');
        foreach ([$organizacion, $finca, $primero, $segundo] as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testDatabaseCheckRejectsAnInvalidAnimalSex(): void
    {
        [, $organizacion, $finca] = $this->persistAnimal('CHECK');
        $this->entityManager->flush();

        $this->expectException(DriverException::class);
        $this->entityManager->getConnection()->insert('animal', [
            'organizacion_id' => $organizacion->getId(),
            'finca_id' => $finca->getId(),
            'codigo_interno' => 'INVALID-'.bin2hex(random_bytes(4)),
            'sexo' => 'DESCONOCIDO',
            'origen' => Animal::ORIGEN_EXTERNO,
            'productora_leche' => 0,
            'estado' => Animal::ESTADO_ACTIVO,
            'fecha_creacion' => '2026-09-17 00:00:00',
            'fecha_actualizacion' => '2026-09-17 00:00:00',
        ]);
    }

    /** @return array{Animal, OrganizacionGanadera, Finca} */
    private function persistAnimal(string $prefix, ?string $arete = null): array
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $finca = (new Finca('Finca '.$suffix, 'FIN-'.$suffix))->setOrganizacion($organizacion);
        $animal = (new Animal($organizacion, $finca, $prefix.'-'.$suffix, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO))->setArete($arete);
        foreach ([$organizacion, $finca, $animal] as $entity) {
            $this->entityManager->persist($entity);
        }

        return [$animal, $organizacion, $finca];
    }
}
