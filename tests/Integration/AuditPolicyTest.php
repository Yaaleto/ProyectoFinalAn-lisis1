<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Pesaje;
use App\Entity\Usuario;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AuditPolicyTest extends KernelTestCase
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

    public function testCreationFillsOnlyTheCreationFieldsFromTheAuthenticatedUser(): void
    {
        $tenant = $this->persistTenant();
        $this->autenticar($tenant['administrador']);

        $animal = $this->nuevoAnimal($tenant);
        $pesaje = new Pesaje($animal, new \DateTimeImmutable('2026-09-17'), '120.00');
        $this->entityManager->persist($animal);
        $this->entityManager->persist($pesaje);
        $this->entityManager->flush();

        foreach ([['animal', 'id_animal', $animal->getId()], ['pesaje', 'id_pesaje', $pesaje->getId()]] as [$tabla, $clave, $id]) {
            $fila = $this->fila($tabla, $clave, $id);
            self::assertSame($tenant['administrador']->getId(), (int) $fila['creado_por'], $tabla);
            self::assertNotNull($fila['fecha_creacion'], $tabla);
            self::assertNull($fila['modificado_por'], $tabla);
            self::assertNull($fila['fecha_actualizacion'], $tabla);
        }
    }

    public function testTheServerAssignsTheAuthorEvenIfAnotherCreatorWasSet(): void
    {
        $tenant = $this->persistTenant();
        $this->autenticar($tenant['encargado']);

        $animal = $this->nuevoAnimal($tenant)->setCreadoPor($tenant['administrador']);
        $this->entityManager->persist($animal);
        $this->entityManager->flush();

        self::assertSame($tenant['encargado']->getId(), (int) $this->fila('animal', 'id_animal', $animal->getId())['creado_por']);
    }

    public function testEveryRealEditUpdatesTheModifierAndDateTogether(): void
    {
        $tenant = $this->persistTenant();
        $this->autenticar($tenant['administrador']);
        $animal = $this->nuevoAnimal($tenant);
        $this->entityManager->persist($animal);
        $this->entityManager->flush();

        $this->autenticar($tenant['encargado']);
        $animal->setNombre('Primera edición');
        $this->entityManager->flush();
        $primera = $this->fila('animal', 'id_animal', $animal->getId());

        self::assertSame($tenant['administrador']->getId(), (int) $primera['creado_por']);
        self::assertSame($tenant['encargado']->getId(), (int) $primera['modificado_por']);
        self::assertNotNull($primera['fecha_actualizacion']);

        $this->autenticar($tenant['administrador']);
        $animal->setNombre('Segunda edición');
        $this->entityManager->flush();
        $segunda = $this->fila('animal', 'id_animal', $animal->getId());

        self::assertSame($tenant['administrador']->getId(), (int) $segunda['creado_por']);
        self::assertSame($tenant['administrador']->getId(), (int) $segunda['modificado_por']);
        self::assertNotNull($segunda['fecha_actualizacion']);
    }

    public function testAFlushWithoutChangesDoesNotRecordAModification(): void
    {
        $tenant = $this->persistTenant();
        $this->autenticar($tenant['administrador']);
        $pesaje = new Pesaje($this->persistAnimal($tenant), new \DateTimeImmutable('2026-09-17'), '120.00');
        $this->entityManager->persist($pesaje);
        $this->entityManager->flush();

        $pesaje->setPesoKg('120.00');
        $this->entityManager->flush();

        $fila = $this->fila('pesaje', 'id_pesaje', $pesaje->getId());
        self::assertNull($fila['modificado_por']);
        self::assertNull($fila['fecha_actualizacion']);
    }

    public function testAnEditWithoutAnAuthenticatedUserIsRejected(): void
    {
        $tenant = $this->persistTenant();
        $this->autenticar($tenant['administrador']);
        $animal = $this->persistAnimal($tenant);
        self::getContainer()->get('security.token_storage')->setToken(null);

        $animal->setNombre('Sin autor');

        $this->expectException(LogicException::class);
        $this->entityManager->flush();
    }

    public function testACreationWithoutAnyAuthorIsRejected(): void
    {
        $tenant = $this->persistTenant();
        $this->entityManager->persist($this->nuevoAnimal($tenant));

        $this->expectException(LogicException::class);
        $this->entityManager->flush();
    }

    public function testAUserReferencedAsCreatorCannotBeDeleted(): void
    {
        $tenant = $this->persistTenant();
        $this->autenticar($tenant['encargado']);
        $this->persistAnimal($tenant);

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->entityManager->getConnection()->delete('usuario', ['id_usuario' => $tenant['encargado']->getId()]);
    }

    public function testAUserReferencedAsModifierCannotBeDeleted(): void
    {
        $tenant = $this->persistTenant();
        $this->autenticar($tenant['administrador']);
        $animal = $this->persistAnimal($tenant);
        $this->autenticar($tenant['encargado']);
        $animal->setNombre('Editado');
        $this->entityManager->flush();
        $this->entityManager->getConnection()->update('animal', ['creado_por' => $tenant['administrador']->getId()], ['id_animal' => $animal->getId()]);

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->entityManager->getConnection()->delete('usuario', ['id_usuario' => $tenant['encargado']->getId()]);
    }

    public function testAuditForeignKeysRestrictDeleteAndUpdateOnEveryAuditedTable(): void
    {
        $reglas = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT kcu.table_name AS table_name, kcu.column_name AS column_name,
                    rc.delete_rule AS delete_rule, rc.update_rule AS update_rule
               FROM information_schema.key_column_usage kcu
               JOIN information_schema.referential_constraints rc
                 ON rc.constraint_schema = kcu.constraint_schema AND rc.constraint_name = kcu.constraint_name
              WHERE kcu.constraint_schema = DATABASE()
                AND kcu.column_name IN ('creado_por', 'modificado_por')
                AND kcu.referenced_table_name = 'usuario'
                AND kcu.referenced_column_name = 'id_usuario'"
        );

        self::assertCount(10, $reglas);
        foreach ($reglas as $regla) {
            self::assertSame(['RESTRICT', 'RESTRICT'], [$regla['delete_rule'], $regla['update_rule']], $regla['table_name'].'.'.$regla['column_name']);
        }
    }

    /** @return array{organizacion: OrganizacionGanadera, finca: Finca, administrador: Usuario, encargado: Usuario} */
    private function persistTenant(): array
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $finca = (new Finca('Finca '.$suffix, 'FIN-'.$suffix))->setOrganizacion($organizacion);
        $administrador = (new Usuario('Administrador '.$suffix, 'admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado '.$suffix, 'manager-'.$suffix.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($finca);
        foreach ([$organizacion, $finca, $administrador, $encargado] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        return compact('organizacion', 'finca', 'administrador', 'encargado');
    }

    private function nuevoAnimal(array $tenant): Animal
    {
        $codigo = strtoupper(bin2hex(random_bytes(6)));

        return (new Animal($tenant['organizacion'], $tenant['finca'], 'AN-'.$codigo, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO))
            ->setArete('GT-'.$codigo);
    }

    private function persistAnimal(array $tenant): Animal
    {
        $animal = $this->nuevoAnimal($tenant);
        $this->entityManager->persist($animal);
        $this->entityManager->flush();

        return $animal;
    }

    private function autenticar(Usuario $usuario): void
    {
        self::getContainer()->get('security.token_storage')
            ->setToken(new UsernamePasswordToken($usuario, 'main', $usuario->getRoles()));
    }

    /** @return array<string, mixed> */
    private function fila(string $tabla, string $clave, ?int $id): array
    {
        return $this->entityManager->getConnection()->fetchAssociative(
            sprintf('SELECT creado_por, fecha_creacion, modificado_por, fecha_actualizacion FROM %s WHERE %s = ?', $tabla, $clave),
            [$id],
        );
    }
}
