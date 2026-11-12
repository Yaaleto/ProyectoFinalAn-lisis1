<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Sin modo de depuración (como en producción) el usuario ve páginas de error propias, no el detalle técnico. */
final class ErrorPagesTest extends WebTestCase
{
    public function testUnknownAddressesShowTheFriendlyNotFoundPage(): void
    {
        $client = static::createClient(['debug' => false]);
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Ganadería '.$sufijo, 'ERR4-'.$sufijo);
        $administrador = (new Usuario('Administrador', 'err4-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))->setOrganizacion($organizacion);
        $em->persist($organizacion);
        $em->persist($administrador);
        $em->flush();

        $client->request('GET', '/direccion-que-no-existe');
        $this->assertFriendlyPage(404, 'Página no encontrada');

        $client->loginUser($administrador);
        $client->request('GET', '/animals/999999999');
        $this->assertFriendlyPage(404, 'Página no encontrada');
    }

    private function assertFriendlyPage(int $codigo, string $titulo): void
    {
        self::assertResponseStatusCodeSame($codigo);
        $contenido = (string) static::getClient()->getResponse()->getContent();
        self::assertStringContainsString($titulo, $contenido);
        self::assertStringContainsString('Volver al inicio', $contenido);
        self::assertStringNotContainsString('Symfony', $contenido);
        self::assertStringNotContainsString('Exception', $contenido);
    }
}
