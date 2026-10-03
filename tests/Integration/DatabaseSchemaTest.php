<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Pesaje;
use App\Entity\Raza;
use App\Entity\Usuario;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseSchemaTest extends KernelTestCase
{
    private const LECHE_PENDIENTE = 'Pendiente de #41: la producción de leche aún no está alineada con el esquema V1.';

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
        [$animal, , , $creador] = $this->persistAnimal('PESAJE');
        $this->entityManager->persist($this->pesaje($animal, '2026-09-17', '120.00', $creador));
        $this->entityManager->persist($this->pesaje($animal, '2026-09-17', '121.00', $creador));

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

    public function testDatabaseRejectsFarmManagerWithoutAssignedFarm(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $encargado = (new Usuario('Encargado '.$suffix, 'sin-finca-'.$suffix.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion);
        $this->entityManager->persist($organizacion);
        $this->entityManager->persist($encargado);

        $this->expectException(DriverException::class);
        $this->entityManager->flush();
    }

    public function testDatabaseRejectsFarmAssignedFromAnotherOrganization(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $otraOrganizacion = new OrganizacionGanadera('Otra organización '.$suffix, 'OTRA-'.$suffix);
        $fincaAjena = (new Finca('Finca ajena', 'AJENA-'.$suffix))->setOrganizacion($otraOrganizacion);
        $this->entityManager->persist($organizacion);
        $this->entityManager->persist($otraOrganizacion);
        $this->entityManager->persist($fincaAjena);
        $this->entityManager->flush();

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->entityManager->getConnection()->insert('usuario', [
            'id_organizacion' => $organizacion->getId(),
            'nombre' => 'Encargado '.$suffix,
            'correo' => 'finca-ajena-'.$suffix.'@example.com',
            'contrasena_hash' => 'hash',
            'rol' => Usuario::ROL_ENCARGADO,
            'finca_activa_id' => $fincaAjena->getId(),
        ]);
    }

    public function testAnimalWithTargetIdentifiersIsStoredThroughTheEntity(): void
    {
        [, $organizacion, $finca, $creador] = $this->persistAnimal('BASE');
        $codigo = 'AN-'.strtoupper(bin2hex(random_bytes(6)));
        $animal = (new Animal($organizacion, $finca, $codigo, Animal::SEXO_HEMBRA, Animal::ORIGEN_NACIDO_EN_FINCA))
            ->setArete('GT-V1')
            ->setCreadoPor($creador);
        $this->entityManager->persist($animal);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $guardado = $this->entityManager->find(Animal::class, $animal->getId());

        self::assertMatchesRegularExpression('/^AN-[0-9A-F]{12}$/', $guardado->getCodigoInterno());
        self::assertSame($codigo, $guardado->getCodigoInterno());
        self::assertSame('GT-V1', $guardado->getArete());
        self::assertSame(Animal::ESTADO_ACTIVO, $guardado->getEstado());
        self::assertFalse($guardado->isProductoraLeche());
        self::assertNotNull($guardado->getCreadoPor());
        self::assertNull($guardado->getActualizadoPor());
    }

    public function testSameEarringIsAllowedInDifferentOrganizations(): void
    {
        [$primero] = $this->persistAnimal('ARETE-A', 'GT-101');
        [$segundo] = $this->persistAnimal('ARETE-B', 'GT-101');

        $this->entityManager->flush();

        self::assertNotNull($primero->getId());
        self::assertNotNull($segundo->getId());
    }

    public function testDatabaseRejectsDuplicateInternalAnimalCodeGlobally(): void
    {
        [$primero, , , $creador] = $this->persistAnimal('CODIGO-GLOBAL');
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Otra organización '.$suffix, 'OTRA-'.$suffix);
        $finca = (new Finca('Otra finca '.$suffix, 'OTRA-FIN-'.$suffix))->setOrganizacion($organizacion);
        $segundo = (new Animal($organizacion, $finca, $primero->getCodigoInterno(), Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO))
            ->setArete('GT-'.$suffix)
            ->setCreadoPor($creador);
        foreach ([$organizacion, $finca, $segundo] as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testDatabaseRejectsDuplicateEarringInsideOneOrganization(): void
    {
        [, $organizacion, $finca, $creador] = $this->persistAnimal('ARETE-DUP', 'GT-101');
        $segundo = (new Animal($organizacion, $finca, 'B-'.bin2hex(random_bytes(4)), Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO))
            ->setArete('GT-101')
            ->setCreadoPor($creador);
        $this->entityManager->persist($segundo);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testDirectSqlAcceptsAValidAnimalRow(): void
    {
        [, $organizacion, $finca, $creador] = $this->persistAnimal('SQL-VALIDO');
        $this->entityManager->flush();

        self::assertSame(1, $this->insertAnimalRow($organizacion, $finca, $creador));
    }

    public function testDirectSqlCannotPlaceAnAnimalOnAnotherOrganizationFarm(): void
    {
        [, $organizacion, , $creador] = $this->persistAnimal('CRUCE-FINCA');
        [, , $fincaAjena] = $this->persistAnimal('CRUCE-FINCA-AJENA');
        $this->entityManager->flush();

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->insertAnimalRow($organizacion, $fincaAjena, $creador);
    }

    public function testDirectSqlCannotUseABreedFromAnotherOrganization(): void
    {
        [, $organizacion, $finca, $creador] = $this->persistAnimal('CRUCE-RAZA');
        [, $otraOrganizacion] = $this->persistAnimal('CRUCE-RAZA-AJENA');
        $razaAjena = new Raza($otraOrganizacion, 'Brahman');
        $this->entityManager->persist($razaAjena);
        $this->entityManager->flush();

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->insertAnimalRow($organizacion, $finca, $creador, ['raza_id' => $razaAjena->getId()]);
    }

    public function testDirectSqlCannotLinkParentsFromAnotherOrganization(): void
    {
        [, $organizacion, $finca, $creador] = $this->persistAnimal('CRUCE-MADRE');
        [$madreAjena] = $this->persistAnimal('CRUCE-MADRE-AJENA');
        $this->entityManager->flush();

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->insertAnimalRow($organizacion, $finca, $creador, ['id_madre' => $madreAjena->getId()]);
    }

    public function testDirectSqlCannotLinkAFatherFromAnotherOrganization(): void
    {
        [, $organizacion, $finca, $creador] = $this->persistAnimal('CRUCE-PADRE');
        [$padreAjeno] = $this->persistAnimal('CRUCE-PADRE-AJENO');
        $this->entityManager->flush();

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->insertAnimalRow($organizacion, $finca, $creador, ['id_padre' => $padreAjeno->getId()]);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidAnimalRows(): iterable
    {
        yield 'sexo desconocido' => [['sexo' => 'OTRO'], 'ck_animal_sexo'];
        yield 'origen desconocido' => [['origen' => 'COMPRADO'], 'ck_animal_origen'];
        yield 'estado desconocido' => [['estado' => 'PERDIDO', 'fecha_salida' => '2026-09-17'], 'ck_animal_estado'];
        yield 'macho productor' => [['sexo' => Animal::SEXO_MACHO, 'es_productora' => 1], 'ck_animal_productora_hembra'];
        yield 'activo con salida' => [['fecha_salida' => '2026-09-17'], 'ck_animal_salida_por_estado'];
        yield 'vendido sin salida' => [['estado' => Animal::ESTADO_VENDIDO], 'ck_animal_salida_por_estado'];
        yield 'peso de nacimiento cero' => [['peso_nacimiento_kg' => '0.00'], 'ck_animal_peso_nacimiento'];
    }

    /** @dataProvider invalidAnimalRows */
    public function testDatabaseChecksRejectInvalidAnimalRows(array $cambios, string $restriccion): void
    {
        [, $organizacion, $finca, $creador] = $this->persistAnimal('CHECK');
        $this->entityManager->flush();

        $this->expectException(DriverException::class);
        $this->expectExceptionMessageMatches('/'.$restriccion.'/');
        $this->insertAnimalRow($organizacion, $finca, $creador, $cambios);
    }

    public function testBreedNameCanBeReusedOnlyAcrossOrganizations(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $primera = new OrganizacionGanadera('Organización uno '.$suffix, 'ORG-ONE-'.$suffix);
        $segunda = new OrganizacionGanadera('Organización dos '.$suffix, 'ORG-TWO-'.$suffix);
        $this->entityManager->persist($primera);
        $this->entityManager->persist($segunda);
        $this->entityManager->persist(new Raza($primera, 'Brahman'));
        $this->entityManager->persist(new Raza($segunda, 'Brahman'));
        $this->entityManager->flush();

        $this->entityManager->persist(new Raza($primera, 'Brahman'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testDatabaseCheckRejectsNonPositiveWeight(): void
    {
        [$animal, $organizacion, , $creador] = $this->persistAnimal('PESO-CHECK');
        $this->entityManager->flush();

        $this->expectException(DriverException::class);
        $this->expectExceptionMessageMatches('/ck_pesaje_peso/');
        $this->entityManager->getConnection()->insert('pesaje', [
            'organizacion_id' => $organizacion->getId(),
            'id_animal' => $animal->getId(),
            'fecha_pesaje' => '2026-09-17',
            'peso_kg' => '0.00',
            'creado_por' => $creador->getId(),
        ]);
    }

    public function testDatabaseCheckRejectsNonPositiveMilkProduction(): void
    {
        self::markTestSkipped(self::LECHE_PENDIENTE);
        [$animal] = $this->persistAnimal('LECHE-CHECK');
        $this->entityManager->flush();

        $this->expectException(DriverException::class);
        $this->entityManager->getConnection()->insert('produccion_leche', [
            'animal_id' => $animal->getId(),
            'fecha_hora' => '2026-09-17 06:00:00',
            'cantidad_litros' => '0.00',
            'fecha_creacion' => '2026-09-17 00:00:00',
            'fecha_actualizacion' => '2026-09-17 00:00:00',
        ]);
    }

    public function testDatabaseRestrictsDeletionOfAnimalWithWeightHistory(): void
    {
        [$animal, , , $creador] = $this->persistAnimal('HISTORIAL');
        $this->entityManager->persist($this->pesaje($animal, '2026-09-17', '120.00', $creador));
        $this->entityManager->flush();

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->entityManager->getConnection()->delete('animal', ['id_animal' => $animal->getId()]);
    }

    public function testWeightHistorySupportsEmptySingleAndOrderedMultipleEntries(): void
    {
        [$animal, , , $creador] = $this->persistAnimal('HISTORIAL-CONSULTA');
        $this->entityManager->flush();
        $connection = $this->entityManager->getConnection();

        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM pesaje WHERE id_animal = ?', [$animal->getId()]));

        $this->entityManager->persist($this->pesaje($animal, '2026-09-16', '110.00', $creador));
        $this->entityManager->flush();
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM pesaje WHERE id_animal = ?', [$animal->getId()]));

        $this->entityManager->persist($this->pesaje($animal, '2026-09-17', '115.00', $creador));
        $this->entityManager->flush();

        self::assertSame(['110.00', '115.00'], $connection->fetchFirstColumn('SELECT peso_kg FROM pesaje WHERE id_animal = ? ORDER BY fecha_pesaje ASC', [$animal->getId()]));
    }

    private function pesaje(Animal $animal, string $fecha, string $peso, Usuario $creador): Pesaje
    {
        return (new Pesaje($animal, new \DateTimeImmutable($fecha), $peso))->setCreadoPor($creador);
    }

    /** @return array{Animal, OrganizacionGanadera, Finca, Usuario} */
    private function persistAnimal(string $prefix, ?string $arete = null): array
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $finca = (new Finca('Finca '.$suffix, 'FIN-'.$suffix))->setOrganizacion($organizacion);
        $creador = (new Usuario('Administrador '.$suffix, 'admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $animal = (new Animal($organizacion, $finca, $prefix.'-'.$suffix, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO))
            ->setArete($arete ?? 'GT-'.$suffix)
            ->setCreadoPor($creador);
        foreach ([$organizacion, $finca, $creador, $animal] as $entity) {
            $this->entityManager->persist($entity);
        }

        return [$animal, $organizacion, $finca, $creador];
    }

    /** Inserta un animal con SQL directo, sin pasar por las validaciones de la entidad. */
    private function insertAnimalRow(OrganizacionGanadera $organizacion, Finca $finca, Usuario $creador, array $cambios = []): int
    {
        return (int) $this->entityManager->getConnection()->insert('animal', array_merge([
            'id_organizacion' => $organizacion->getId(),
            'id_finca' => $finca->getId(),
            'codigo_interno' => 'AN-'.strtoupper(bin2hex(random_bytes(6))),
            'numero_arete' => 'SQL-'.strtoupper(bin2hex(random_bytes(4))),
            'sexo' => Animal::SEXO_HEMBRA,
            'origen' => Animal::ORIGEN_EXTERNO,
            'creado_por' => $creador->getId(),
        ], $cambios));
    }
}
