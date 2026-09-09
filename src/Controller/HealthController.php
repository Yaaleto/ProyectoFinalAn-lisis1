<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/', name: 'app_health', methods: ['GET'])]
    public function __invoke(Connection $connection): Response
    {
        return $this->render('health/index.html.twig', [
            'databaseConnected' => (int) $connection->fetchOne('SELECT 1') === 1,
        ]);
    }
}
