<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Finca;
use App\Security\Voter\FincaVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FincaAccessController extends AbstractController
{
    #[Route('/farms/{finca}', name: 'app_farm_show', methods: ['GET'], requirements: ['finca' => '\\d+'])]
    public function show(Finca $finca): Response
    {
        $this->denyAccessUnlessGranted(FincaVoter::VIEW, $finca);

        return $this->render('farm/show.html.twig', ['finca' => $finca]);
    }
}
