<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use App\Security\UsuarioPasswordManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InactiveAccessTest extends WebTestCase
{
    public function testUserOfAnInactiveOrganizationCannotSignIn(): void
    {
        $client = static::createClient();
        $usuario = $this->createAdministrator($client, organizacionActiva: false);

        $this->signIn($client, $usuario->getEmail());
        $client->followRedirect();

        self::assertStringContainsString('Esta organización está inactiva.', (string) $client->getResponse()->getContent());
    }

    public function testOpenSessionEndsWhenTheOrganizationIsDeactivated(): void
    {
        $client = static::createClient();
        $usuario = $this->createAdministrator($client);
        $client->loginUser($usuario);
        $client->request('GET', '/farms');
        self::assertResponseIsSuccessful();

        $this->entityManager($client)->getConnection()->update(
            'organizacion_ganadera',
            ['activo' => 0],
            ['id_organizacion' => $usuario->getOrganizacion()?->getId()],
        );

        $client->request('GET', '/farms');
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertStringContainsString('Esta organización está inactiva. Tu sesión se cerró.', (string) $client->getResponse()->getContent());

        $client->request('GET', '/farms');
        self::assertResponseRedirects();
        self::assertStringEndsWith('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testOpenSessionEndsWhenTheAccountIsDeactivated(): void
    {
        $client = static::createClient();
        $usuario = $this->createAdministrator($client);
        $client->loginUser($usuario);
        $client->request('GET', '/users');
        self::assertResponseIsSuccessful();

        $this->entityManager($client)->getConnection()->update(
            'usuario',
            ['activo' => 0],
            ['id_usuario' => $usuario->getId()],
        );

        $client->request('GET', '/users');
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertStringContainsString('Esta cuenta está inactiva. Tu sesión se cerró.', (string) $client->getResponse()->getContent());
    }

    private function createAdministrator(KernelBrowser $client, bool $organizacionActiva = true): Usuario
    {
        $entityManager = $this->entityManager($client);
        $suffix = strtolower(bin2hex(random_bytes(4)));
        $organizacion = (new OrganizacionGanadera('Organización '.$suffix, 'INACT-'.strtoupper($suffix)))
            ->setActivo($organizacionActiva);
        $usuario = (new Usuario('Administrador', 'inactive-'.$suffix.'@example.com', '', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        (new UsuarioPasswordManager($client->getContainer()->get(UserPasswordHasherInterface::class)))
            ->setPassword($usuario, 'correct-password');

        $entityManager->persist($organizacion);
        $entityManager->persist($usuario);
        $entityManager->flush();

        return $usuario;
    }

    private function signIn(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/login');
        $client->request('POST', '/login', [
            '_username' => $email,
            '_password' => 'correct-password',
            '_csrf_token' => $crawler->filterXPath('//input[@name="_csrf_token"]')->attr('value'),
        ]);
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
