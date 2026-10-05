<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\MontaReproductiva;
use App\Entity\OrganizacionGanadera;
use App\Entity\Pesaje;
use App\Entity\ProduccionLeche;
use App\Entity\Usuario;
use App\Entity\Vacuna;
use App\Entity\Vacunacion;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OperationalRecordSchemaTest extends KernelTestCase
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

    public function testRecordsSavedThroughEntitiesTakeTheAnimalOrganization(): void
    {
        $propia = $this->persistTenant('PROPIA');
        $pesaje = (new Pesaje($propia['hembra'], new \DateTimeImmutable('2026-09-17'), '120.00'))
            ->setCreadoPor($propia['creador']);
        $vacunacion = (new Vacunacion($propia['hembra'], $propia['vacuna'], new \DateTimeImmutable('2026-09-17'), '5 ml'))
            ->setCreadoPor($propia['creador']);
        $monta = (new MontaReproductiva($propia['hembra'], $propia['macho'], new \DateTimeImmutable('2026-09-17')))
            ->setCreadoPor($propia['creador']);
        foreach ([$pesaje, $vacunacion, $monta] as $registro) {
            $this->entityManager->persist($registro);
        }
        $this->entityManager->flush();

        $connection = $this->entityManager->getConnection();
        $organizacionId = $propia['organizacion']->getId();
        self::assertSame($organizacionId, (int) $connection->fetchOne('SELECT organizacion_id FROM pesaje WHERE id_pesaje = ?', [$pesaje->getId()]));
        self::assertSame($organizacionId, (int) $connection->fetchOne('SELECT organizacion_id FROM vacunacion WHERE id_vacunacion = ?', [$vacunacion->getId()]));
        self::assertSame($organizacionId, (int) $connection->fetchOne('SELECT organizacion_id FROM monta_reproductiva WHERE id_monta = ?', [$monta->getId()]));
    }

    public function testDirectSqlAcceptsRecordsInsideOneOrganization(): void
    {
        $propia = $this->persistTenant('VALIDO');

        self::assertSame(1, $this->insertPesaje($propia, $propia));
        self::assertSame(1, $this->insertVacunacion($propia, $propia, $propia));
        self::assertSame(1, $this->insertMonta($propia, $propia, $propia));
    }

    public function testDirectSqlCannotWeighAnAnimalFromAnotherOrganization(): void
    {
        $propia = $this->persistTenant('PESAJE');
        $ajena = $this->persistTenant('PESAJE-AJENA');

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->insertPesaje($propia, $ajena);
    }

    public function testDirectSqlCannotVaccinateAnAnimalFromAnotherOrganization(): void
    {
        $propia = $this->persistTenant('VAC-ANIMAL');
        $ajena = $this->persistTenant('VAC-ANIMAL-AJENA');

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->insertVacunacion($propia, $ajena, $propia);
    }

    public function testDirectSqlCannotApplyAVaccineFromAnotherOrganization(): void
    {
        $propia = $this->persistTenant('VAC-VACUNA');
        $ajena = $this->persistTenant('VAC-VACUNA-AJENA');

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->insertVacunacion($propia, $propia, $ajena);
    }

    public function testDirectSqlCannotRecordAMatingForAFemaleFromAnotherOrganization(): void
    {
        $propia = $this->persistTenant('MONTA-HEMBRA');
        $ajena = $this->persistTenant('MONTA-HEMBRA-AJENA');

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->insertMonta($propia, $ajena, $propia);
    }

    public function testDirectSqlCannotRecordAMatingWithAMaleFromAnotherOrganization(): void
    {
        $propia = $this->persistTenant('MONTA-MACHO');
        $ajena = $this->persistTenant('MONTA-MACHO-AJENA');

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->insertMonta($propia, $propia, $ajena);
    }

    public function testSeveralMilkingsOfOneAnimalOnTheSameDateAreValid(): void
    {
        $propia = $this->persistTenant('ORDENOS');
        foreach (['2026-09-17 05:30:00', '2026-09-17 17:00:00'] as $fechaHora) {
            $this->entityManager->persist($this->ordeno($propia, $propia['finca'], $fechaHora, '8.50'));
        }
        $this->entityManager->flush();

        $total = $this->entityManager->getConnection()->fetchOne(
            'SELECT SUM(cantidad_litros) FROM produccion_leche WHERE id_animal = ? AND DATE(fecha_hora) = ?',
            [$propia['hembra']->getId(), '2026-09-17'],
        );

        self::assertSame('17.00', $total);
    }

    public function testMilkingKeepsItsFarmAfterTheAnimalIsMoved(): void
    {
        $propia = $this->persistTenant('TRASLADO');
        $fincaNueva = (new Finca('Finca nueva', 'NUEVA-'.strtoupper(bin2hex(random_bytes(3)))))->setOrganizacion($propia['organizacion']);
        $this->entityManager->persist($fincaNueva);
        $ordeno = $this->ordeno($propia, $propia['finca'], '2026-09-17 06:00:00', '9.00');
        $this->entityManager->persist($ordeno);
        $this->entityManager->flush();

        $propia['hembra']->setFinca($fincaNueva);
        $this->entityManager->flush();

        $connection = $this->entityManager->getConnection();
        self::assertSame($propia['finca']->getId(), (int) $connection->fetchOne('SELECT id_finca_ordeno FROM produccion_leche WHERE id_produccion = ?', [$ordeno->getId()]));
        self::assertSame($propia['organizacion']->getId(), (int) $connection->fetchOne('SELECT organizacion_id FROM produccion_leche WHERE id_produccion = ?', [$ordeno->getId()]));
    }

    public function testDirectSqlAcceptsAMilkingInsideOneOrganization(): void
    {
        $propia = $this->persistTenant('LECHE-VALIDA');

        self::assertSame(1, $this->insertProduccion($propia, $propia, $propia));
    }

    public function testDirectSqlRequiresTheMilkingFarm(): void
    {
        $propia = $this->persistTenant('LECHE-SIN-FINCA');

        $this->expectException(NotNullConstraintViolationException::class);
        $this->insertProduccion($propia, $propia, ['finca' => null]);
    }

    public function testDirectSqlCannotRecordMilkFromAnAnimalOfAnotherOrganization(): void
    {
        $propia = $this->persistTenant('LECHE-ANIMAL');
        $ajena = $this->persistTenant('LECHE-ANIMAL-AJENA');

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->insertProduccion($propia, $ajena, $propia);
    }

    public function testDirectSqlCannotRecordMilkOnAFarmOfAnotherOrganization(): void
    {
        $propia = $this->persistTenant('LECHE-FINCA');
        $ajena = $this->persistTenant('LECHE-FINCA-AJENA');

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->insertProduccion($propia, $propia, $ajena);
    }

    /** @return array{organizacion: OrganizacionGanadera, finca: Finca, creador: Usuario, hembra: Animal, macho: Animal, vacuna: Vacuna} */
    private function persistTenant(string $prefix): array
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $finca = (new Finca('Finca '.$suffix, 'FIN-'.$suffix))->setOrganizacion($organizacion);
        $creador = (new Usuario('Administrador '.$suffix, 'admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $hembra = (new Animal($organizacion, $finca, $prefix.'-H-'.$suffix, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO))
            ->setArete('H-'.$suffix)
            ->setProductoraLeche(true)
            ->setCreadoPor($creador);
        $macho = (new Animal($organizacion, $finca, $prefix.'-M-'.$suffix, Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO))
            ->setArete('M-'.$suffix)
            ->setCreadoPor($creador);
        $vacuna = new Vacuna($organizacion, 'Vacuna '.$suffix);
        foreach ([$organizacion, $finca, $creador, $hembra, $macho, $vacuna] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        return compact('organizacion', 'finca', 'creador', 'hembra', 'macho', 'vacuna');
    }

    private function ordeno(array $tenant, Finca $finca, string $fechaHora, string $litros): ProduccionLeche
    {
        return (new ProduccionLeche($tenant['hembra'], $finca, new \DateTimeImmutable($fechaHora), $litros))
            ->setCreadoPor($tenant['creador']);
    }

    private function insertProduccion(array $registro, array $animal, array $finca): int
    {
        return (int) $this->entityManager->getConnection()->insert('produccion_leche', [
            'organizacion_id' => $registro['organizacion']->getId(),
            'id_animal' => $animal['hembra']->getId(),
            'id_finca_ordeno' => $finca['finca']?->getId(),
            'fecha_hora' => '2026-09-17 06:00:00',
            'cantidad_litros' => '8.50',
            'creado_por' => $registro['creador']->getId(),
        ]);
    }

    private function insertPesaje(array $registro, array $animal): int
    {
        return (int) $this->entityManager->getConnection()->insert('pesaje', [
            'organizacion_id' => $registro['organizacion']->getId(),
            'id_animal' => $animal['hembra']->getId(),
            'fecha_pesaje' => '2026-09-17',
            'peso_kg' => '120.00',
            'creado_por' => $registro['creador']->getId(),
        ]);
    }

    private function insertVacunacion(array $registro, array $animal, array $vacuna): int
    {
        return (int) $this->entityManager->getConnection()->insert('vacunacion', [
            'organizacion_id' => $registro['organizacion']->getId(),
            'id_animal' => $animal['hembra']->getId(),
            'id_vacuna' => $vacuna['vacuna']->getId(),
            'fecha_vacunacion' => '2026-09-17',
            'dosis' => '5 ml',
            'creado_por' => $registro['creador']->getId(),
        ]);
    }

    private function insertMonta(array $registro, array $hembra, array $macho): int
    {
        return (int) $this->entityManager->getConnection()->insert('monta_reproductiva', [
            'organizacion_id' => $registro['organizacion']->getId(),
            'id_hembra' => $hembra['hembra']->getId(),
            'id_macho' => $macho['macho']->getId(),
            'fecha_monta' => '2026-09-17',
            'creado_por' => $registro['creador']->getId(),
        ]);
    }
}
