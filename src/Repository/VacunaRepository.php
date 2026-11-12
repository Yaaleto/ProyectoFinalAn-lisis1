<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrganizacionGanadera;
use App\Entity\Vacuna;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Vacuna> */
final class VacunaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vacuna::class);
    }

    public function createOrganizationQueryBuilder(OrganizacionGanadera $organizacion, string $alias = 'vacuna'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere(sprintf('%s.organizacion = :organizacion', $alias))
            ->setParameter('organizacion', $organizacion);
    }

    public function createActiveOrganizationQueryBuilder(OrganizacionGanadera $organizacion, string $alias = 'vacuna'): QueryBuilder
    {
        return $this->createOrganizationQueryBuilder($organizacion, $alias)
            ->andWhere(sprintf('%s.activa = :activa', $alias))
            ->setParameter('activa', true);
    }
}
