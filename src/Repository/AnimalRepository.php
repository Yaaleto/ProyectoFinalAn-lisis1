<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Animal;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<Animal> */
final class AnimalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Animal::class);
    }

    /**
     * Animales a los que el usuario puede asignar registros operativos nuevos: los de su ámbito
     * (toda la organización o la finca del Encargado) que están en una finca activa.
     */
    public function createRegistrableQueryBuilder(Usuario $usuario, string $alias = 'animal'): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($alias)
            ->addSelect('finca')
            ->join(sprintf('%s.finca', $alias), 'finca')
            ->andWhere(sprintf('%s.organizacion = :organizacion', $alias))
            ->andWhere('finca.activo = :fincaActiva')
            ->setParameter('organizacion', $usuario->getOrganizacion())
            ->setParameter('fincaActiva', true)
            ->orderBy(sprintf('%s.arete', $alias), SortDirection::Ascending);

        if (Usuario::ROL_ADMINISTRADOR !== $usuario->getRol()) {
            $queryBuilder->andWhere(sprintf('%s.finca = :fincaUsuario', $alias))->setParameter('fincaUsuario', $usuario->getFincaActiva());
        }

        return $queryBuilder;
    }
}
