<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use App\Security\UsuarioPasswordManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserAdministrationTest extends WebTestCase
{
    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/users');

        self::assertResponseRedirects('/login');
    }

    public function testAdministratorListsOnlyUsersFromItsOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', '/users');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($fixture['encargado']->getEmail(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($fixture['usuarioAjeno']->getEmail(), (string) $client->getResponse()->getContent());
    }

    public function testAdministratorCreatesManagerInItsOrganizationWithFarmPasswordAndAuditData(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $email = 'new-manager-'.strtolower(bin2hex(random_bytes(4))).'@example.com';

        $crawler = $client->request('GET', '/users/new');
        $client->submit($crawler->selectButton('Crear usuario')->form([
            'usuario[nombre]' => 'Nuevo encargado',
            'usuario[email]' => $email,
            'usuario[rol]' => Usuario::ROL_ENCARGADO,
            'usuario[fincaActiva]' => (string) $fixture['finca']->getId(),
            'usuario[activo]' => '1',
            'usuario[plainPassword]' => 'secure-password',
        ]));

        self::assertResponseRedirects('/users');
        $usuario = $this->entityManager($client)->getRepository(Usuario::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(Usuario::class, $usuario);
        self::assertSame($fixture['organizacion']->getId(), $usuario->getOrganizacion()?->getId());
        self::assertSame($fixture['finca']->getId(), $usuario->getFincaActiva()?->getId());
        self::assertTrue($this->passwordHasher($client)->isPasswordValid($usuario, 'secure-password'));
    }

    public function testManagerCannotBeCreatedWithoutAnActiveFarm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $email = 'no-farm-'.strtolower(bin2hex(random_bytes(4))).'@example.com';

        $crawler = $client->request('GET', '/users/new');
        $client->submit($crawler->selectButton('Crear usuario')->form([
            'usuario[nombre]' => 'Encargado sin finca',
            'usuario[email]' => $email,
            'usuario[rol]' => Usuario::ROL_ENCARGADO,
            'usuario[fincaActiva]' => '',
            'usuario[activo]' => '1',
            'usuario[plainPassword]' => 'secure-password',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Selecciona una finca activa para el encargado.', (string) $client->getResponse()->getContent());
        self::assertNull($this->entityManager($client)->getRepository(Usuario::class)->findOneBy(['email' => $email]));
    }

    public function testAdministratorCannotAssignFarmFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $email = 'foreign-farm-'.strtolower(bin2hex(random_bytes(4))).'@example.com';

        $crawler = $client->request('GET', '/users/new');
        $token = $crawler->filterXPath('//input[@name="usuario[_token]"]')->attr('value');
        $client->request('POST', '/users/new', ['usuario' => [
            'nombre' => 'Asignación ajena',
            'email' => $email,
            'rol' => Usuario::ROL_ENCARGADO,
            'fincaActiva' => (string) $fixture['fincaAjena']->getId(),
            'activo' => '1',
            'plainPassword' => 'secure-password',
            '_token' => $token,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager($client)->getRepository(Usuario::class)->findOneBy(['email' => $email]));
    }

    public function testInactiveFarmIsNotOfferedForAssignment(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $fixture['segundaFinca']->setActivo(false);
        $this->entityManager($client)->flush();
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/users/new');

        $opciones = $crawler->filterXPath('//select[@name="usuario[fincaActiva]"]/option')->each(static fn ($option) => $option->attr('value'));
        self::assertContains((string) $fixture['finca']->getId(), $opciones);
        self::assertNotContains((string) $fixture['segundaFinca']->getId(), $opciones);
    }

    public function testManagerCannotBeAssignedToAnInactiveFarm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $fixture['segundaFinca']->setActivo(false);
        $this->entityManager($client)->flush();
        $client->loginUser($fixture['administrador']);
        $email = 'inactive-farm-'.strtolower(bin2hex(random_bytes(4))).'@example.com';

        $crawler = $client->request('GET', '/users/new');
        $token = $crawler->filterXPath('//input[@name="usuario[_token]"]')->attr('value');
        $client->request('POST', '/users/new', ['usuario' => [
            'nombre' => 'Asignación inactiva',
            'email' => $email,
            'rol' => Usuario::ROL_ENCARGADO,
            'fincaActiva' => (string) $fixture['segundaFinca']->getId(),
            'activo' => '1',
            'plainPassword' => 'secure-password',
            '_token' => $token,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager($client)->getRepository(Usuario::class)->findOneBy(['email' => $email]));
    }

    public function testEditingManagerReplacesItsActiveFarmAndKeepsPasswordWhenLeftBlank(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $managerId = $fixture['encargado']->getId();
        $oldHash = $fixture['encargado']->getPassword();

        $crawler = $client->request('GET', sprintf('/users/%d/edit', $managerId));
        $client->submit($crawler->selectButton('Guardar cambios')->form([
            'usuario[nombre]' => 'Encargado actualizado',
            'usuario[email]' => $fixture['encargado']->getEmail(),
            'usuario[rol]' => Usuario::ROL_ENCARGADO,
            'usuario[fincaActiva]' => (string) $fixture['segundaFinca']->getId(),
            'usuario[activo]' => '1',
            'usuario[plainPassword]' => '',
        ]));

        self::assertResponseRedirects('/users');
        $this->entityManager($client)->clear();
        $usuario = $this->entityManager($client)->find(Usuario::class, $managerId);
        self::assertSame('Encargado actualizado', $usuario?->getNombre());
        self::assertSame($fixture['segundaFinca']->getId(), $usuario?->getFincaActiva()?->getId());
        self::assertSame($oldHash, $usuario?->getPassword());
    }

    public function testChangingUserToAdministratorRemovesItsFarmAssignment(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $managerId = $fixture['encargado']->getId();

        $crawler = $client->request('GET', sprintf('/users/%d/edit', $managerId));
        $client->submit($crawler->selectButton('Guardar cambios')->form([
            'usuario[nombre]' => $fixture['encargado']->getNombre(),
            'usuario[email]' => $fixture['encargado']->getEmail(),
            'usuario[rol]' => Usuario::ROL_ADMINISTRADOR,
            'usuario[fincaActiva]' => (string) $fixture['finca']->getId(),
            'usuario[activo]' => '1',
            'usuario[plainPassword]' => '',
        ]));

        self::assertResponseRedirects('/users');
        $this->entityManager($client)->clear();
        $usuario = $this->entityManager($client)->find(Usuario::class, $managerId);
        self::assertSame(Usuario::ROL_ADMINISTRADOR, $usuario?->getRol());
        self::assertNull($usuario?->getFincaActiva());
    }

    public function testAdministratorCanChangePasswordAndDeactivateAccount(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $managerId = $fixture['encargado']->getId();

        $crawler = $client->request('GET', sprintf('/users/%d/edit', $managerId));
        $client->submit($crawler->selectButton('Guardar cambios')->form([
            'usuario[nombre]' => $fixture['encargado']->getNombre(),
            'usuario[email]' => $fixture['encargado']->getEmail(),
            'usuario[rol]' => Usuario::ROL_ENCARGADO,
            'usuario[fincaActiva]' => (string) $fixture['finca']->getId(),
            'usuario[activo]' => false,
            'usuario[plainPassword]' => 'replacement-password',
        ]));

        self::assertResponseRedirects('/users');
        $this->entityManager($client)->clear();
        $usuario = $this->entityManager($client)->find(Usuario::class, $managerId);
        self::assertInstanceOf(Usuario::class, $usuario);
        self::assertFalse($usuario->isActivo());
        self::assertTrue($this->passwordHasher($client)->isPasswordValid($usuario, 'replacement-password'));
    }

    public function testAdministratorCannotDeactivateOrDemoteItself(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $adminId = $fixture['administrador']->getId();

        foreach ([
            ['usuario[activo]' => false],
            ['usuario[rol]' => Usuario::ROL_ENCARGADO, 'usuario[fincaActiva]' => (string) $fixture['finca']->getId()],
        ] as $cambios) {
            $crawler = $client->request('GET', sprintf('/users/%d/edit', $adminId));
            $client->submit($crawler->selectButton('Guardar cambios')->form($cambios));
            self::assertResponseStatusCodeSame(422);
        }
        self::assertStringContainsString('No puedes quitarte el rol de Administrador.', (string) $client->getResponse()->getContent());

        $this->entityManager($client)->clear();
        $administrador = $this->entityManager($client)->find(Usuario::class, $adminId);
        self::assertTrue($administrador?->isActivo());
        self::assertSame(Usuario::ROL_ADMINISTRADOR, $administrador?->getRol());

        $crawler = $client->request('GET', sprintf('/users/%d/edit', $adminId));
        $client->submit($crawler->selectButton('Guardar cambios')->form(['usuario[nombre]' => 'Nombre corregido']));
        self::assertResponseRedirects('/users');
    }

    public function testAnotherAdministratorCanDeactivateAnAdministrator(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $otro = (new Usuario('Otro administrador', 'otro-'.strtolower(bin2hex(random_bytes(4))).'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($fixture['organizacion']);
        $this->entityManager($client)->persist($otro);
        $this->entityManager($client)->flush();
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', sprintf('/users/%d/edit', $otro->getId()));
        $client->submit($crawler->selectButton('Guardar cambios')->form(['usuario[activo]' => false]));

        self::assertResponseRedirects('/users');
        $this->entityManager($client)->clear();
        self::assertFalse($this->entityManager($client)->find(Usuario::class, $otro->getId())?->isActivo());
    }

    public function testDuplicateEmailAndShortPasswordAreRejected(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/users/new');
        $client->submit($crawler->selectButton('Crear usuario')->form([
            'usuario[nombre]' => 'Correo duplicado',
            'usuario[email]' => $fixture['encargado']->getEmail(),
            'usuario[rol]' => Usuario::ROL_ENCARGADO,
            'usuario[fincaActiva]' => (string) $fixture['finca']->getId(),
            'usuario[activo]' => '1',
            'usuario[plainPassword]' => 'short',
        ]));

        self::assertResponseStatusCodeSame(422);
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('El correo electrónico ya está en uso.', $content);
        self::assertStringContainsString('La contraseña debe contener al menos 8 caracteres.', $content);
    }

    public function testAdministratorCannotEditUserFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', sprintf('/users/%d/edit', $fixture['usuarioAjeno']->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testFarmManagerCannotAccessAnyUserAdministrationScreen(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        foreach (['/users', '/users/new', sprintf('/users/%d/edit', $fixture['administrador']->getId())] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('/', null, $path);
            $client->followRedirect();
            self::assertStringContainsString('Tu cuenta no tiene permiso para administrar usuarios.', (string) $client->getResponse()->getContent());
        }
    }

    public function testInvalidCsrfTokenDoesNotCreateUser(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $email = 'invalid-csrf-'.strtolower(bin2hex(random_bytes(4))).'@example.com';

        $client->request('POST', '/users/new', ['usuario' => [
            'nombre' => 'Usuario sin token',
            'email' => $email,
            'rol' => Usuario::ROL_ENCARGADO,
            'fincaActiva' => (string) $fixture['finca']->getId(),
            'activo' => '1',
            'plainPassword' => 'secure-password',
            '_token' => 'invalid-token',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager($client)->getRepository(Usuario::class)->findOneBy(['email' => $email]));
    }

    /** @return array{organizacion: OrganizacionGanadera, administrador: Usuario, encargado: Usuario, usuarioAjeno: Usuario, finca: Finca, segundaFinca: Finca, fincaAjena: Finca} */
    private function createFixture(KernelBrowser $client): array
    {
        $entityManager = $this->entityManager($client);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'USR-'.$suffix);
        $otraOrganizacion = new OrganizacionGanadera('Otra organización '.$suffix, 'USR-OTHER-'.$suffix);
        $finca = (new Finca('Finca principal '.$suffix, 'MAIN-'.$suffix))->setOrganizacion($organizacion);
        $segundaFinca = (new Finca('Finca secundaria '.$suffix, 'SECOND-'.$suffix))->setOrganizacion($organizacion);
        $fincaAjena = (new Finca('Finca ajena '.$suffix, 'FOREIGN-'.$suffix))->setOrganizacion($otraOrganizacion);
        $administrador = (new Usuario('Administrador '.$suffix, 'user-admin-'.strtolower($suffix).'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado '.$suffix, 'user-manager-'.strtolower($suffix).'@example.com', 'existing-hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($finca);
        $usuarioAjeno = (new Usuario('Usuario ajeno '.$suffix, 'user-foreign-'.strtolower($suffix).'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($otraOrganizacion);

        foreach ([$organizacion, $otraOrganizacion, $finca, $segundaFinca, $fincaAjena, $administrador, $encargado, $usuarioAjeno] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('organizacion', 'administrador', 'encargado', 'usuarioAjeno', 'finca', 'segundaFinca', 'fincaAjena');
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }

    private function passwordHasher(KernelBrowser $client): UserPasswordHasherInterface
    {
        return $client->getContainer()->get(UserPasswordHasherInterface::class);
    }
}
