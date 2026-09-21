<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\OrganizacionGanadera;
use App\Entity\Finca;
use App\Entity\Raza;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BreedCatalogTest extends WebTestCase
{
    public function testAdministratorListsOnlyBreedsFromItsOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', '/breeds');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($fixture['raza']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($fixture['razaAjena']->getNombre(), (string) $client->getResponse()->getContent());
    }

    public function testAdministratorCreatesBreedInsideItsOrganizationWithAuditData(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $nombre = 'Brahman '.bin2hex(random_bytes(3));

        $crawler = $client->request('GET', '/breeds/new');
        $client->submit($crawler->selectButton('Crear raza')->form(['raza[nombre]' => $nombre]));

        self::assertResponseRedirects('/breeds');
        $raza = $this->entityManager($client)->getRepository(Raza::class)->findOneBy(['nombre' => $nombre]);
        self::assertInstanceOf(Raza::class, $raza);
        self::assertSame($fixture['organizacion']->getId(), $raza->getOrganizacion()->getId());
        self::assertSame($fixture['administrador']->getId(), $raza->getCreadoPor()?->getId());
        self::assertSame($fixture['administrador']->getId(), $raza->getActualizadoPor()?->getId());
    }

    public function testDuplicateNameInSameOrganizationIsRejected(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/breeds/new');
        $client->submit($crawler->selectButton('Crear raza')->form(['raza[nombre]' => $fixture['raza']->getNombre()]));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('La raza ya existe en la organización.', (string) $client->getResponse()->getContent());
    }

    public function testSameNameCanBeUsedByDifferentOrganizations(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/breeds/new');
        $client->submit($crawler->selectButton('Crear raza')->form(['raza[nombre]' => $fixture['razaAjena']->getNombre()]));

        self::assertResponseRedirects('/breeds');
        self::assertInstanceOf(Raza::class, $this->entityManager($client)->getRepository(Raza::class)->findOneBy([
            'organizacion' => $fixture['organizacion'],
            'nombre' => $fixture['razaAjena']->getNombre(),
        ]));
    }

    public function testAdministratorEditsBreedAndRecordsUpdater(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $id = $fixture['raza']->getId();
        $administradorId = $fixture['administrador']->getId();

        $crawler = $client->request('GET', sprintf('/breeds/%d/edit', $id));
        $client->submit($crawler->selectButton('Guardar cambios')->form(['raza[nombre]' => 'Raza actualizada']));

        self::assertResponseRedirects('/breeds');
        $this->entityManager($client)->clear();
        $raza = $this->entityManager($client)->find(Raza::class, $id);
        self::assertSame('Raza actualizada', $raza?->getNombre());
        self::assertSame($administradorId, $raza?->getActualizadoPor()?->getId());
    }

    public function testAdministratorCannotEditBreedFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', sprintf('/breeds/%d/edit', $fixture['razaAjena']->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCanDeactivateAndReactivateABreed(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/breeds');
        $client->submit($crawler->selectButton('Desactivar')->form());

        self::assertResponseRedirects('/breeds');
        $this->entityManager($client)->clear();
        self::assertFalse($this->entityManager($client)->find(Raza::class, $fixture['raza']->getId())?->isActiva());

        $crawler = $client->request('GET', '/breeds');
        $client->submit($crawler->selectButton('Activar')->form());

        $this->entityManager($client)->clear();
        self::assertTrue($this->entityManager($client)->find(Raza::class, $fixture['raza']->getId())?->isActiva());
    }

    public function testFarmManagerSeesOnlyActiveBreedsAndCannotManageThem(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $fixture['raza']->setActiva(false);
        $this->entityManager($client)->flush();
        $client->loginUser($fixture['encargado']);

        $client->request('GET', '/breeds');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($fixture['razaAjena']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($fixture['raza']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('Nueva raza', (string) $client->getResponse()->getContent());

        $client->request('GET', '/breeds/new');
        self::assertResponseRedirects('/');
    }

    /** @return array{organizacion: OrganizacionGanadera, administrador: Usuario, encargado: Usuario, raza: Raza, razaAjena: Raza} */
    private function createFixture(KernelBrowser $client): array
    {
        $entityManager = $this->entityManager($client);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $otraOrganizacion = new OrganizacionGanadera('Otra organización '.$suffix, 'OTRA-'.$suffix);
        $finca = (new Finca('Finca '.$suffix, 'FIN-'.$suffix))->setOrganizacion($otraOrganizacion);
        $administrador = (new Usuario('Administrador', 'admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado', 'manager-'.$suffix.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($otraOrganizacion)
            ->setFincaActiva($finca);
        $raza = new Raza($organizacion, 'Raza propia '.$suffix);
        $razaAjena = new Raza($otraOrganizacion, 'Raza ajena '.$suffix);

        foreach ([$organizacion, $otraOrganizacion, $finca, $administrador, $encargado, $raza, $razaAjena] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('organizacion', 'administrador', 'encargado', 'raza', 'razaAjena');
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
