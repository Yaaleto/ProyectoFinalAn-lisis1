<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Pesaje;
use App\Entity\Usuario;
use App\Entity\Vacuna;
use App\Entity\Vacunacion;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * RNF-P-07: una operación que afecta más de una tabla se ejecuta como transacción; si falla a mitad,
 * no queda ningún registro parcialmente almacenado. A diferencia de las demás pruebas de integración,
 * esta no envuelve el caso en una transacción propia, para observar el commit y el rollback reales.
 */
final class TransactionIntegrityTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    /** @var list<string> */
    private array $limpieza = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach ($this->limpieza as $sql) {
            $connection->executeStatement($sql);
        }
        parent::tearDown();
    }

    public function testAFailureInTheLastTableRollsBackEveryTableOfTheOperation(): void
    {
        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $base = $this->persistirBase($sufijo);
        $this->entityManager->persist((new Pesaje($base['animal'], new DateTimeImmutable('2026-03-01'), '400.00'))->setCreadoPor($base['creador']));
        $this->entityManager->flush();
        $this->entityManager->clear();
        $animal = $this->entityManager->find(Animal::class, $base['animal']->getId());
        $creador = $this->entityManager->find(Usuario::class, $base['creador']->getId());

        // Una sola operación que escribe en vacuna, vacunación y pesaje; el pesaje repite la fecha y falla al final.
        $vacuna = new Vacuna($animal->getOrganizacion(), 'Vacuna '.$sufijo);
        $this->entityManager->persist($vacuna);
        $this->entityManager->persist((new Vacunacion($animal, $vacuna, new DateTimeImmutable('2026-03-02'), '5 ml'))->setCreadoPor($creador));
        $this->entityManager->persist((new Pesaje($animal, new DateTimeImmutable('2026-03-01'), '401.00'))->setCreadoPor($creador));

        try {
            $this->entityManager->flush();
            self::fail('Se esperaba que la operación fallara por el pesaje duplicado.');
        } catch (UniqueConstraintViolationException) {
            // Interrupción esperada.
        }

        $connection = $this->entityManager->getConnection();
        self::assertFalse($connection->isTransactionActive());
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM vacuna WHERE nombre = ?', ['Vacuna '.$sufijo]));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM vacunacion WHERE id_animal = ?', [$animal->getId()]));
        self::assertSame(['400.00'], $connection->fetchFirstColumn('SELECT peso_kg FROM pesaje WHERE id_animal = ?', [$animal->getId()]));
    }

    /** @return array{animal: Animal, creador: Usuario} */
    private function persistirBase(string $sufijo): array
    {
        $organizacion = new OrganizacionGanadera('Transacción '.$sufijo, 'TX-'.$sufijo);
        $finca = (new Finca('Finca '.$sufijo, 'TX-'.$sufijo))->setOrganizacion($organizacion);
        $creador = (new Usuario('Administrador', 'tx-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))->setOrganizacion($organizacion);
        $animal = (new Animal($organizacion, $finca, 'AN-TX-'.$sufijo, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01')))
            ->setArete('TX-'.$sufijo)
            ->setCreadoPor($creador);
        foreach ([$organizacion, $finca, $creador, $animal] as $entidad) {
            $this->entityManager->persist($entidad);
        }
        $this->entityManager->flush();

        $id = $organizacion->getId();
        $this->limpieza = [
            "DELETE FROM pesaje WHERE organizacion_id = $id",
            "DELETE FROM vacunacion WHERE organizacion_id = $id",
            "DELETE FROM vacuna WHERE id_organizacion = $id",
            "DELETE FROM animal WHERE id_organizacion = $id",
            "DELETE FROM usuario WHERE id_organizacion = $id",
            "DELETE FROM finca WHERE id_organizacion = $id",
            "DELETE FROM organizacion_ganadera WHERE id_organizacion = $id",
        ];

        return ['animal' => $animal, 'creador' => $creador];
    }
}
