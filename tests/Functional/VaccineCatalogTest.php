<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use App\Entity\Vacuna;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VaccineCatalogTest extends WebTestCase
{
    public function testAdministratorListsOnlyVaccinesFromItsOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', '/vaccines');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($fixture['vacuna']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($fixture['vacunaAjena']->getNombre(), (string) $client->getResponse()->getContent());
    }

    public function testCatalogNavigationGroupsBreedAndVaccineLinks(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', '/vaccines');

        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<details open>', $content);
        self::assertStringContainsString('>Catálogos</span>', $content);
        self::assertStringContainsString('class="ml-auto size-4 shrink-0 fill-current"', $content);
        self::assertStringContainsString('href="/breeds"', $content);
        self::assertStringContainsString('href="/vaccines"', $content);
    }

    public function testAdministratorCreatesVaccineInsideItsOrganizationWithAuditData(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $nombre = 'Clostridial '.bin2hex(random_bytes(3));

        $crawler = $client->request('GET', '/vaccines/new');
        $client->submit($crawler->selectButton('Crear vacuna')->form([
            'vacuna[nombre]' => $nombre,
            'vacuna[descripcion]' => 'Aplicación anual.',
        ]));

        self::assertResponseRedirects('/vaccines');
        $vacuna = $this->entityManager($client)->getRepository(Vacuna::class)->findOneBy(['nombre' => $nombre]);
        self::assertInstanceOf(Vacuna::class, $vacuna);
        self::assertSame('Aplicación anual.', $vacuna->getDescripcion());
        self::assertSame($fixture['organizacion']->getId(), $vacuna->getOrganizacion()->getId());
        self::assertSame($fixture['administrador']->getId(), $vacuna->getCreadoPor()?->getId());
        self::assertSame($fixture['administrador']->getId(), $vacuna->getActualizadoPor()?->getId());
    }

    public function testDuplicateNameInSameOrganizationIsRejected(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/vaccines/new');
        $client->submit($crawler->selectButton('Crear vacuna')->form(['vacuna[nombre]' => $fixture['vacuna']->getNombre()]));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('La vacuna ya existe en la organización.', (string) $client->getResponse()->getContent());
    }

    public function testSameNameCanBeUsedByDifferentOrganizations(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/vaccines/new');
        $client->submit($crawler->selectButton('Crear vacuna')->form(['vacuna[nombre]' => $fixture['vacunaAjena']->getNombre()]));

        self::assertResponseRedirects('/vaccines');
        self::assertInstanceOf(Vacuna::class, $this->entityManager($client)->getRepository(Vacuna::class)->findOneBy([
            'organizacion' => $fixture['organizacion'],
            'nombre' => $fixture['vacunaAjena']->getNombre(),
        ]));
    }

    public function testAdministratorEditsVaccineAndRecordsUpdater(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $id = $fixture['vacuna']->getId();
        $administradorId = $fixture['administrador']->getId();

        $crawler = $client->request('GET', sprintf('/vaccines/%d/edit', $id));
        $client->submit($crawler->selectButton('Guardar cambios')->form([
            'vacuna[nombre]' => 'Vacuna actualizada',
            'vacuna[descripcion]' => 'Nueva descripción.',
        ]));

        self::assertResponseRedirects('/vaccines');
        $this->entityManager($client)->clear();
        $vacuna = $this->entityManager($client)->find(Vacuna::class, $id);
        self::assertSame('Vacuna actualizada', $vacuna?->getNombre());
        self::assertSame('Nueva descripción.', $vacuna?->getDescripcion());
        self::assertSame($administradorId, $vacuna?->getActualizadoPor()?->getId());
    }

    public function testAdministratorCannotEditVaccineFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', sprintf('/vaccines/%d/edit', $fixture['vacunaAjena']->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCanDeactivateAndReactivateAVaccine(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/vaccines');
        $client->submit($crawler->selectButton('Desactivar')->form());

        self::assertResponseRedirects('/vaccines');
        $this->entityManager($client)->clear();
        self::assertFalse($this->entityManager($client)->find(Vacuna::class, $fixture['vacuna']->getId())?->isActiva());

        $crawler = $client->request('GET', '/vaccines');
        $client->submit($crawler->selectButton('Activar')->form());

        $this->entityManager($client)->clear();
        self::assertTrue($this->entityManager($client)->find(Vacuna::class, $fixture['vacuna']->getId())?->isActiva());
    }

    public function testFarmManagerSeesOnlyActiveVaccinesAndCannotManageThem(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $fixture['vacunaAjena']->setActiva(false);
        $this->entityManager($client)->flush();
        $client->loginUser($fixture['encargado']);

        $client->request('GET', '/vaccines');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString($fixture['vacuna']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertStringContainsString($fixture['vacunaDisponible']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($fixture['vacunaAjena']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('Nueva vacuna', (string) $client->getResponse()->getContent());

        $client->request('GET', '/vaccines/new');
        self::assertResponseRedirects('/');
    }

    /** @return array{organizacion: OrganizacionGanadera, administrador: Usuario, encargado: Usuario, vacuna: Vacuna, vacunaAjena: Vacuna, vacunaDisponible: Vacuna} */
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
        $vacuna = new Vacuna($organizacion, 'Vacuna propia '.$suffix);
        $vacunaAjena = new Vacuna($otraOrganizacion, 'Vacuna ajena '.$suffix);
        $vacunaDisponible = new Vacuna($otraOrganizacion, 'Vacuna disponible '.$suffix);

        foreach ([$organizacion, $otraOrganizacion, $finca, $administrador, $encargado, $vacuna, $vacunaAjena, $vacunaDisponible] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('organizacion', 'administrador', 'encargado', 'vacuna', 'vacunaAjena', 'vacunaDisponible');
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
