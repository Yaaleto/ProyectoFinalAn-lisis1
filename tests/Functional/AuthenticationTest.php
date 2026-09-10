<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthenticationTest extends WebTestCase
{
    public function testActiveAdministratorCanSignIn(): void
    {
        $client = static::createClient();
        $this->createUser($client, 'admin-auth@example.com', Usuario::ROL_ADMINISTRADOR);

        $this->signIn($client, 'admin-auth@example.com');

        self::assertResponseRedirects('/');
    }

    public function testActiveFarmManagerCanSignIn(): void
    {
        $client = static::createClient();
        $this->createUser($client, 'manager-auth@example.com', Usuario::ROL_ENCARGADO);

        $this->signIn($client, 'manager-auth@example.com');

        self::assertResponseRedirects('/');
    }

    public function testInvalidCredentialsAreRejected(): void
    {
        $client = static::createClient();
        $this->createUser($client, 'invalid-auth@example.com', Usuario::ROL_ADMINISTRADOR);

        $this->signIn($client, 'invalid-auth@example.com', 'incorrect-password');
        $client->followRedirect();

        self::assertStringContainsString('El correo o la contraseña no son correctos.', (string) $client->getResponse()->getContent());
    }

    public function testLoginFormRequiresAtLeastEightPasswordCharacters(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertStringContainsString('minlength="8"', (string) $client->getResponse()->getContent());
    }

    public function testInactiveAccountCannotSignIn(): void
    {
        $client = static::createClient();
        $this->createUser($client, 'inactive-auth@example.com', Usuario::ROL_ADMINISTRADOR, false);

        $this->signIn($client, 'inactive-auth@example.com');
        $client->followRedirect();

        self::assertStringContainsString('Esta cuenta está inactiva.', (string) $client->getResponse()->getContent());
    }

    public function testLogoutEndsTheAuthenticatedSession(): void
    {
        $client = static::createClient();
        $this->createUser($client, 'logout-auth@example.com', Usuario::ROL_ADMINISTRADOR);
        $this->signIn($client, 'logout-auth@example.com');
        $client->followRedirect();

        $crawler = $client->getCrawler();
        $token = $crawler->filterXPath('//form[@action="/logout"]//input[@name="_csrf_token"]')->attr('value');
        $client->request('POST', '/logout', ['_csrf_token' => $token]);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Iniciar sesión', (string) $client->getResponse()->getContent());
    }

    private function createUser($client, string $email, string $rol, bool $activo = true): void
    {
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $organizationCode = 'AUTH-'.strtoupper(strtok($email, '@'));
        $entityManager->createQuery('DELETE FROM App\\Entity\\Usuario u WHERE u.email = :email')
            ->setParameter('email', $email)
            ->execute();
        $entityManager->createQuery('DELETE FROM App\\Entity\\OrganizacionGanadera o WHERE o.codigo = :code')
            ->setParameter('code', $organizationCode)
            ->execute();

        $organization = new OrganizacionGanadera('Organización de prueba', $organizationCode);
        $user = (new Usuario('Usuario de prueba', $email, '', $rol))
            ->setOrganizacion($organization)
            ->setActivo($activo);
        $passwordHasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $user->setContrasenaHash($passwordHasher->hashPassword($user, 'correct-password'));

        $entityManager->persist($organization);
        $entityManager->persist($user);
        $entityManager->flush();
        $entityManager->clear();
    }

    private function signIn($client, string $email, string $password = 'correct-password'): void
    {
        $crawler = $client->request('GET', '/login');
        $token = $crawler->filterXPath('//input[@name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', [
            '_username' => $email,
            '_password' => $password,
            '_csrf_token' => $token,
        ]);
    }
}
