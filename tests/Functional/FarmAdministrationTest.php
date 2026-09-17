<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FarmAdministrationTest extends WebTestCase
{
    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/farms');

        self::assertResponseRedirects('/login');
    }

    public function testAdministratorListsOnlyFarmsFromItsOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', '/farms');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<h1', (string) $client->getResponse()->getContent());
        self::assertStringContainsString($fixture['finca']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($fixture['fincaAjena']->getNombre(), (string) $client->getResponse()->getContent());
    }

    public function testAdministratorCreatesFarmInsideItsOrganizationWithAuditData(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $codigo = 'NEW-'.strtoupper(bin2hex(random_bytes(3)));

        $crawler = $client->request('GET', '/farms/new');
        $client->submit($crawler->selectButton('Crear finca')->form([
            'finca[nombre]' => 'Finca Nueva',
            'finca[codigo]' => $codigo,
        ]));

        self::assertResponseRedirects('/farms');
        $finca = $this->entityManager($client)->getRepository(Finca::class)->findOneBy(['codigo' => $codigo]);
        self::assertInstanceOf(Finca::class, $finca);
        self::assertSame($fixture['organizacion']->getId(), $finca->getOrganizacion()?->getId());
        self::assertSame($fixture['administrador']->getId(), $finca->getCreadoPor()?->getId());
        self::assertSame($fixture['administrador']->getId(), $finca->getActualizadoPor()?->getId());
    }

    public function testDuplicateCodeInSameOrganizationIsRejectedWithoutCreatingFarm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/farms/new');
        $client->submit($crawler->selectButton('Crear finca')->form([
            'finca[nombre]' => 'Finca Duplicada',
            'finca[codigo]' => $fixture['finca']->getCodigo(),
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('El código de finca ya existe en la organización.', (string) $client->getResponse()->getContent());
        self::assertNull($this->entityManager($client)->getRepository(Finca::class)->findOneBy(['nombre' => 'Finca Duplicada']));
    }

    public function testSameCodeCanBeUsedByDifferentOrganizations(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/farms/new');
        $client->submit($crawler->selectButton('Crear finca')->form([
            'finca[nombre]' => 'Código Compartido',
            'finca[codigo]' => $fixture['fincaAjena']->getCodigo(),
        ]));

        self::assertResponseRedirects('/farms');
        $finca = $this->entityManager($client)->getRepository(Finca::class)->findOneBy([
            'organizacion' => $fixture['organizacion'],
            'codigo' => $fixture['fincaAjena']->getCodigo(),
        ]);
        self::assertInstanceOf(Finca::class, $finca);
    }

    public function testBlankAndOversizedValuesAreRejected(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/farms/new');
        $client->submit($crawler->selectButton('Crear finca')->form([
            'finca[nombre]' => '',
            'finca[codigo]' => str_repeat('X', 51),
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager($client)->getRepository(Finca::class)->findOneBy(['codigo' => str_repeat('X', 51)]));
    }

    public function testAdministratorEditsFarmAndRecordsUpdater(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $id = $fixture['finca']->getId();

        $crawler = $client->request('GET', sprintf('/farms/%d/edit', $id));
        $client->submit($crawler->selectButton('Guardar cambios')->form([
            'finca[nombre]' => 'Finca Actualizada',
            'finca[codigo]' => $fixture['finca']->getCodigo(),
        ]));

        self::assertResponseRedirects('/farms');
        $this->entityManager($client)->clear();
        $finca = $this->entityManager($client)->find(Finca::class, $id);
        self::assertSame('Finca Actualizada', $finca?->getNombre());
        self::assertSame($fixture['administrador']->getId(), $finca?->getActualizadoPor()?->getId());
    }

    public function testAdministratorCannotEditFarmFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', sprintf('/farms/%d/edit', $fixture['fincaAjena']->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testFarmManagerCannotAccessAnyAdministrationScreen(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        foreach (['/farms', '/farms/new', sprintf('/farms/%d/edit', $fixture['finca']->getId())] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('/', null, $path);
            $client->followRedirect();
            self::assertStringContainsString('Tu cuenta no tiene permiso para administrar fincas.', (string) $client->getResponse()->getContent());
        }
    }

    public function testInvalidCsrfTokenDoesNotCreateFarm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $codigo = 'CSRF-'.strtoupper(bin2hex(random_bytes(3)));

        $client->request('POST', '/farms/new', ['finca' => [
            'nombre' => 'Finca sin token',
            'codigo' => $codigo,
            '_token' => 'invalid-token',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager($client)->getRepository(Finca::class)->findOneBy(['codigo' => $codigo]));
    }

    /** @return array{organizacion: OrganizacionGanadera, administrador: Usuario, encargado: Usuario, finca: Finca, fincaAjena: Finca} */
    private function createFixture(KernelBrowser $client): array
    {
        $entityManager = $this->entityManager($client);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $otraOrganizacion = new OrganizacionGanadera('Otra organización '.$suffix, 'OTRA-'.$suffix);
        $finca = (new Finca('Finca propia '.$suffix, 'FIN-'.$suffix))->setOrganizacion($organizacion);
        $fincaAjena = (new Finca('Finca ajena '.$suffix, 'AJENA-'.$suffix))->setOrganizacion($otraOrganizacion);
        $administrador = (new Usuario('Administrador', 'admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado', 'manager-'.$suffix.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($finca);

        foreach ([$organizacion, $otraOrganizacion, $finca, $fincaAjena, $administrador, $encargado] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('organizacion', 'administrador', 'encargado', 'finca', 'fincaAjena');
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
