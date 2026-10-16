<?php

declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Punto de comprobación público para el registro de disponibilidad (RNF-P-08).
 * Solo informa si la aplicación y la base de datos responden; no expone datos de la organización.
 */
final class EstadoController extends AbstractController
{
    #[Route('/status', name: 'app_status', methods: ['GET'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $connection->fetchOne('SELECT 1');
            $estado = 'ok';
        } catch (Throwable) {
            $estado = 'sin_base_de_datos';
        }

        $respuesta = new JsonResponse(
            ['estado' => $estado, 'hora' => (new DateTimeImmutable())->format(DATE_ATOM)],
            'ok' === $estado ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
        $respuesta->headers->set('Cache-Control', 'no-store');

        return $respuesta;
    }
}
