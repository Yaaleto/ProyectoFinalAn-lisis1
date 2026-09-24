<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AnimalController extends AbstractController
{
    #[Route('/animals', name: 'app_animal_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $usuario = $this->getUser();
        \assert($usuario instanceof Usuario);

        $organizacion = $usuario->getOrganizacion();
        if (null === $organizacion) {
            throw $this->createAccessDeniedException();
        }

        $queryBuilder = $entityManager->getRepository(Animal::class)
            ->createQueryBuilder('animal')
            ->addSelect('finca', 'raza')
            ->join('animal.finca', 'finca')
            ->leftJoin('animal.raza', 'raza')
            ->andWhere('animal.organizacion = :organizacion')
            ->setParameter('organizacion', $organizacion)
            ->orderBy('animal.codigoInterno');

        if (Usuario::ROL_ENCARGADO === $usuario->getRol()) {
            $fincaActiva = $usuario->getFincaActiva();
            if (null === $fincaActiva) {
                $this->addFlash('warning', 'Tu cuenta todavía no tiene una finca asignada.');

                return $this->redirectToRoute('app_health');
            }

            $queryBuilder
                ->andWhere('animal.finca = :fincaActiva')
                ->setParameter('fincaActiva', $fincaActiva);
        }

        return $this->render('animal/index.html.twig', [
            'animales' => $queryBuilder->getQuery()->getResult(),
        ]);
    }
}
