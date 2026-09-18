<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Usuario> */
final class UsuarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Usuario::class);
    }

    public function createOrganizationQueryBuilder(OrganizacionGanadera $organizacion, string $alias = 'usuario'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere(sprintf('%s.organizacion = :organizacion', $alias))
            ->setParameter('organizacion', $organizacion);
    }
}
