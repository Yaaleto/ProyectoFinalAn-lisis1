<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Finca;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/** @extends ServiceEntityRepository<Finca> */
final class FincaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Finca::class);
    }

    public function createAuthorizedQueryBuilder(Usuario $usuario, string $alias = 'finca'): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($alias);
        $organizacion = $usuario->getOrganizacion();

        if (null === $organizacion) {
            return $queryBuilder->where('1 = 0');
        }

        $queryBuilder
            ->andWhere(sprintf('%s.organizacion = :organizacion', $alias))
            ->setParameter('organizacion', $organizacion);

        if (Usuario::ROL_ADMINISTRADOR === $usuario->getRol()) {
            return $queryBuilder;
        }

        if (Usuario::ROL_ENCARGADO !== $usuario->getRol()) {
            return $queryBuilder->andWhere('1 = 0');
        }

        $fincaActiva = $usuario->getFincaActiva();

        if (null === $fincaActiva) {
            return $queryBuilder->andWhere('1 = 0');
        }

        return $queryBuilder
            ->andWhere(sprintf('%s = :fincaActiva', $alias))
            ->setParameter('fincaActiva', $fincaActiva);
    }
}
