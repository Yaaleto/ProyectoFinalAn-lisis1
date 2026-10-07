<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Animal;
use App\Entity\MontaReproductiva;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<MontaReproductiva> */
final class MontaReproductivaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MontaReproductiva::class);
    }

    /**
     * Montas visibles para el usuario: toda su organización o, si es Encargado, las de hembras de su finca.
     * El ámbito lo marca la hembra, porque el macho puede pertenecer a otra finca (CA-08-D).
     */
    public function createAuthorizedQueryBuilder(Usuario $usuario): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('monta')
            ->addSelect('hembra', 'finca', 'macho')
            ->join('monta.hembra', 'hembra')
            ->join('hembra.finca', 'finca')
            ->leftJoin('monta.macho', 'macho')
            ->andWhere('monta.organizacion = :organizacion')
            ->setParameter('organizacion', $usuario->getOrganizacion())
            ->orderBy('monta.fecha', SortDirection::Descending)
            ->addOrderBy('hembra.arete', SortDirection::Ascending);

        if (Usuario::ROL_ADMINISTRADOR !== $usuario->getRol()) {
            $queryBuilder->andWhere('hembra.finca = :fincaUsuario')->setParameter('fincaUsuario', $usuario->getFincaActiva());
        }

        return $queryBuilder;
    }

    /**
     * Historial reproductivo cronológico del animal, como hembra o como macho, dentro del ámbito del usuario.
     *
     * @return list<MontaReproductiva>
     */
    public function historial(Animal $animal, Usuario $usuario): array
    {
        return $this->createAuthorizedQueryBuilder($usuario)
            ->andWhere('(monta.hembra = :animal OR monta.macho = :animal)')
            ->setParameter('animal', $animal)
            ->orderBy('monta.fecha', SortDirection::Ascending)
            ->addOrderBy('monta.id', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
