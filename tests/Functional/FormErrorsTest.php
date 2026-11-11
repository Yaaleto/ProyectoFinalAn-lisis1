<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FormErrorsTest extends WebTestCase
{
    public function testValidationErrorsAreAnnouncedAndLinkedToTheirField(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Ganadería '.$sufijo, 'ERR-'.$sufijo);
        $administrador = (new Usuario('Administrador', 'err-admin-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))->setOrganizacion($organizacion);
        $em->persist($organizacion);
        $em->persist($administrador);
        $em->flush();

        $client->loginUser($administrador);
        $crawler = $client->request('GET', '/breeds/new');
        self::assertCount(0, $crawler->filterXPath('//*[@aria-invalid]'));

        $crawler = $client->submit($crawler->selectButton('Crear raza')->form(['raza[nombre]' => '']));

        self::assertResponseStatusCodeSame(422);
        $campo = $crawler->filterXPath('//input[@name="raza[nombre]"]');
        self::assertSame('true', $campo->attr('aria-invalid'));
        $mensaje = $crawler->filterXPath(sprintf('//*[@id="%s"]', $campo->attr('aria-errormessage')));
        self::assertCount(1, $mensaje);
        self::assertSame('alert', $mensaje->attr('role'));
        self::assertNotSame('', trim($mensaje->text()));
    }
}
