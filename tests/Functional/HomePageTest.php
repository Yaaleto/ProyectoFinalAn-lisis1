<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomePageTest extends WebTestCase
{
    public function testHomeOffersTheUsualTasksInSpanishForBothRoles(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Ganadería '.$sufijo, 'INI-'.$sufijo);
        $finca = (new Finca('Finca Inicio '.$sufijo, 'INI-'.$sufijo))->setOrganizacion($organizacion);
        $administrador = (new Usuario('Administrador', 'ini-admin-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado', 'ini-enc-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ENCARGADO))->setOrganizacion($organizacion)->setFincaActiva($finca);
        foreach ([$organizacion, $finca, $administrador, $encargado] as $entidad) {
            $em->persist($entidad);
        }
        $em->flush();

        foreach ([[$administrador, 'Ganadería '.$sufijo], [$encargado, 'Finca Inicio '.$sufijo]] as [$usuario, $ambito]) {
            $client->loginUser($usuario);
            $crawler = $client->request('GET', '/');

            self::assertResponseIsSuccessful();
            $contenido = (string) $client->getResponse()->getContent();
            self::assertStringContainsString($ambito, $contenido);
            self::assertStringNotContainsString('Dashboard', $contenido);
            self::assertStringNotContainsString('Symfony', $crawler->filterXPath('//main')->text());
            foreach (['/animals/new', '/animals/new/birth', '/weights/new', '/vaccinations/new', '/matings/new', '/milk/new'] as $ruta) {
                self::assertCount(1, $crawler->filterXPath(sprintf('//main//a[@href="%s"]', $ruta)), $ruta);
            }
            self::assertCount(1, $crawler->filterXPath('//main//form[@action="/animals"]//input[@name="q"]'));
        }
    }
}
