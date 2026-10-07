<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Animal;
use App\Entity\Usuario;
use App\Entity\Vacunacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<Vacunacion> */
final class VacunacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vacunacion::class);
    }

    /** Vacunaciones visibles para el usuario: toda su organización o, si es Encargado, su finca. */
    public function createAuthorizedQueryBuilder(Usuario $usuario): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('vacunacion')
            ->addSelect('animal', 'finca', 'vacuna')
            ->join('vacunacion.animal', 'animal')
            ->join('animal.finca', 'finca')
            ->join('vacunacion.vacuna', 'vacuna')
            ->andWhere('vacunacion.organizacion = :organizacion')
            ->setParameter('organizacion', $usuario->getOrganizacion())
            ->orderBy('vacunacion.fecha', SortDirection::Descending)
            ->addOrderBy('animal.arete', SortDirection::Ascending);

        if (Usuario::ROL_ADMINISTRADOR !== $usuario->getRol()) {
            $queryBuilder->andWhere('animal.finca = :fincaUsuario')->setParameter('fincaUsuario', $usuario->getFincaActiva());
        }

        return $queryBuilder;
    }

    /** @return list<Vacunacion> Historial cronológico del animal, incluidas las vacunas ya inactivas. */
    public function historial(Animal $animal): array
    {
        return $this->createQueryBuilder('vacunacion')
            ->addSelect('vacuna')
            ->join('vacunacion.vacuna', 'vacuna')
            ->andWhere('vacunacion.animal = :animal')
            ->setParameter('animal', $animal)
            ->orderBy('vacunacion.fecha', SortDirection::Ascending)
            ->addOrderBy('vacunacion.id', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
