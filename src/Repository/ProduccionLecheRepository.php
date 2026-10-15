<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Animal;
use App\Entity\ProduccionLeche;
use App\Entity\Usuario;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<ProduccionLeche> */
final class ProduccionLecheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProduccionLeche::class);
    }

    /**
     * Ordeños visibles para el usuario: toda su organización o, si es Encargado, los ocurridos en su finca.
     * El ámbito lo marca la finca del ordeño, no la finca actual del animal.
     */
    public function createAuthorizedQueryBuilder(Usuario $usuario): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('ordeno')
            ->addSelect('animal', 'fincaOrdeno')
            ->join('ordeno.animal', 'animal')
            ->join('ordeno.fincaOrdeno', 'fincaOrdeno')
            ->andWhere('ordeno.organizacion = :organizacion')
            ->setParameter('organizacion', $usuario->getOrganizacion())
            ->orderBy('ordeno.fechaHora', SortDirection::Descending);

        if (Usuario::ROL_ADMINISTRADOR !== $usuario->getRol()) {
            $queryBuilder->andWhere('ordeno.fincaOrdeno = :fincaUsuario')->setParameter('fincaUsuario', $usuario->getFincaActiva());
        }

        return $queryBuilder;
    }

    /**
     * Historial cronológico de ordeños del animal dentro del ámbito del usuario: el Encargado solo ve
     * los ordeños ocurridos en su finca (RF-10).
     *
     * @return list<ProduccionLeche>
     */
    public function historial(Animal $animal, Usuario $usuario): array
    {
        return $this->createAuthorizedQueryBuilder($usuario)
            ->andWhere('ordeno.animal = :animal')
            ->setParameter('animal', $animal)
            ->orderBy('ordeno.fechaHora', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * Total diario por animal dentro del ámbito del usuario: agrupa por la parte de fecha de fecha_hora (RF-09).
     *
     * @return list<array{fecha: string, ordenos: int, litros: string}>
     */
    public function totalesDiarios(Animal $animal, Usuario $usuario): array
    {
        $sql = 'SELECT DATE(fecha_hora) AS fecha, COUNT(*) AS ordenos, SUM(cantidad_litros) AS litros
                  FROM produccion_leche
                 WHERE id_animal = :animal';
        $parametros = ['animal' => $animal->getId()];
        if (Usuario::ROL_ADMINISTRADOR !== $usuario->getRol()) {
            $sql .= ' AND id_finca_ordeno = :finca';
            $parametros['finca'] = $usuario->getFincaActiva()?->getId();
        }

        return array_map(
            static fn (array $fila): array => ['fecha' => (string) $fila['fecha'], 'ordenos' => (int) $fila['ordenos'], 'litros' => (string) $fila['litros']],
            $this->getEntityManager()->getConnection()->fetchAllAssociative($sql.' GROUP BY DATE(fecha_hora) ORDER BY fecha ASC', $parametros),
        );
    }

    /**
     * Total por finca en un día: usa la finca del ordeño (id_finca_ordeno), no la finca actual del animal,
     * de modo que el total no cambia si el animal se traslada después (RN-08).
     *
     * @return list<array{finca: string, ordenos: int, litros: string}>
     */
    public function totalesPorFinca(Usuario $usuario, DateTimeImmutable $dia): array
    {
        $sql = 'SELECT finca.nombre AS finca, COUNT(*) AS ordenos, SUM(ordeno.cantidad_litros) AS litros
                  FROM produccion_leche ordeno
                  JOIN finca ON finca.id_finca = ordeno.id_finca_ordeno
                 WHERE ordeno.organizacion_id = :organizacion
                   AND ordeno.fecha_hora >= :desde AND ordeno.fecha_hora < :hasta';
        $parametros = [
            'organizacion' => $usuario->getOrganizacion()?->getId(),
            'desde' => $dia->format('Y-m-d 00:00:00'),
            'hasta' => $dia->modify('+1 day')->format('Y-m-d 00:00:00'),
        ];
        if (Usuario::ROL_ADMINISTRADOR !== $usuario->getRol()) {
            $sql .= ' AND ordeno.id_finca_ordeno = :finca';
            $parametros['finca'] = $usuario->getFincaActiva()?->getId();
        }

        return array_map(
            static fn (array $fila): array => ['finca' => (string) $fila['finca'], 'ordenos' => (int) $fila['ordenos'], 'litros' => (string) $fila['litros']],
            $this->getEntityManager()->getConnection()->fetchAllAssociative($sql.' GROUP BY finca.id_finca, finca.nombre ORDER BY finca.nombre', $parametros),
        );
    }
}
