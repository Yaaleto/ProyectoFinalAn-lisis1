<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Animal;
use App\Entity\Pesaje;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/** @extends ServiceEntityRepository<Pesaje> */
final class PesajeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pesaje::class);
    }

    /** Pesajes visibles para el usuario: toda su organización o, si es Encargado, su finca. */
    public function createAuthorizedQueryBuilder(Usuario $usuario): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('pesaje')
            ->addSelect('animal', 'finca')
            ->join('pesaje.animal', 'animal')
            ->join('animal.finca', 'finca')
            ->andWhere('pesaje.organizacion = :organizacion')
            ->setParameter('organizacion', $usuario->getOrganizacion())
            ->orderBy('pesaje.fecha', SortDirection::Descending)
            ->addOrderBy('animal.arete', SortDirection::Ascending);

        if (Usuario::ROL_ADMINISTRADOR !== $usuario->getRol()) {
            $queryBuilder->andWhere('animal.finca = :fincaUsuario')->setParameter('fincaUsuario', $usuario->getFincaActiva());
        }

        return $queryBuilder;
    }

    /**
     * Historial cronológico del animal con la variación respecto al pesaje anterior (RF-06).
     *
     * @return list<array{pesaje: Pesaje, variacion: ?string}>
     */
    public function historialConVariacion(Animal $animal): array
    {
        /** @var list<Pesaje> $pesajes */
        $pesajes = $this->createQueryBuilder('pesaje')
            ->andWhere('pesaje.animal = :animal')
            ->setParameter('animal', $animal)
            ->orderBy('pesaje.fecha', SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        $historial = [];
        $anterior = null;
        foreach ($pesajes as $pesaje) {
            $historial[] = [
                'pesaje' => $pesaje,
                'variacion' => null === $anterior ? null : number_format((float) $pesaje->getPesoKg() - (float) $anterior, 2, '.', ''),
            ];
            $anterior = $pesaje->getPesoKg();
        }

        return $historial;
    }
}
