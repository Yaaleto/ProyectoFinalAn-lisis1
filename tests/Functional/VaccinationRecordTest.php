<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use App\Entity\Vacuna;
use App\Entity\Vacunacion;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VaccinationRecordTest extends WebTestCase
{
    public function testAdministratorRegistersAVaccinationThroughTheForm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $this->submitNew($client, [
            'animal' => (string) $fixture['animal']->getId(),
            'vacuna' => (string) $fixture['vacuna']->getId(),
            'fecha' => '2026-03-01',
            'dosis' => ' 5 ml ',
            'observaciones' => 'Refuerzo anual',
        ]);

        self::assertResponseRedirects(sprintf('/animals/%d/vaccinations', $fixture['animal']->getId()));
        $vacunacion = $this->vacunaciones($client, $fixture['animal'])[0] ?? null;
        self::assertInstanceOf(Vacunacion::class, $vacunacion);
        self::assertSame($fixture['vacuna']->getId(), $vacunacion->getVacuna()->getId());
        self::assertSame('5 ml', $vacunacion->getDosis());
        self::assertSame('Refuerzo anual', $vacunacion->getObservaciones());
        self::assertSame($fixture['organizacion']->getId(), $vacunacion->getOrganizacion()->getId());
        self::assertSame($fixture['administrador']->getId(), $vacunacion->getCreadoPor()?->getId());
    }

    public function testObservationsAreOptionalButTheDoseIsRequired(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $base = ['animal' => (string) $fixture['animal']->getId(), 'vacuna' => (string) $fixture['vacuna']->getId(), 'fecha' => '2026-03-01'];

        $this->submitNew($client, $base + ['dosis' => '', 'observaciones' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('La dosis es obligatoria.', (string) $client->getResponse()->getContent());

        $this->submitNew($client, $base + ['dosis' => '2 ml', 'observaciones' => '']);
        self::assertResponseRedirects();
        self::assertNull($this->vacunaciones($client, $fixture['animal'])[0]->getObservaciones());
    }

    public function testInactiveOrForeignVaccinesAreNotOfferedOrAcceptedForNewRecords(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/vaccinations/new');
        $opciones = $crawler->filterXPath('//select[@name="vacunacion[vacuna]"]/option')->each(static fn ($option) => $option->attr('value'));
        self::assertContains((string) $fixture['vacuna']->getId(), $opciones);
        self::assertNotContains((string) $fixture['vacunaInactiva']->getId(), $opciones);
        self::assertNotContains((string) $fixture['vacunaAjena']->getId(), $opciones);

        foreach (['vacunaInactiva', 'vacunaAjena'] as $clave) {
            $this->submitNew($client, [
                'animal' => (string) $fixture['animal']->getId(),
                'vacuna' => (string) $fixture[$clave]->getId(),
                'fecha' => '2026-03-01',
                'dosis' => '5 ml',
            ]);
            self::assertResponseStatusCodeSame(422, $clave);
        }
        self::assertCount(0, $this->vacunaciones($client, $fixture['animal']));
    }

    public function testInvalidDatesAreRejected(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        foreach ([
            '2026-01-09' => 'La fecha del registro no puede ser anterior al nacimiento del animal.',
            (new DateTimeImmutable('tomorrow'))->format('Y-m-d') => 'La fecha de vacunación no puede estar en el futuro.',
        ] as $fecha => $mensaje) {
            $this->submitNew($client, [
                'animal' => (string) $fixture['animal']->getId(),
                'vacuna' => (string) $fixture['vacuna']->getId(),
                'fecha' => $fecha,
                'dosis' => '5 ml',
            ]);
            self::assertResponseStatusCodeSame(422, $fecha);
            self::assertStringContainsString($mensaje, (string) $client->getResponse()->getContent());
        }
    }

    public function testFarmManagerCannotVaccinateAnimalsOfAnotherFarm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $crawler = $client->request('GET', '/vaccinations/new');
        $opciones = $crawler->filterXPath('//select[@name="vacunacion[animal]"]/option')->each(static fn ($option) => $option->attr('value'));
        self::assertContains((string) $fixture['animal']->getId(), $opciones);
        self::assertNotContains((string) $fixture['animalOtraFinca']->getId(), $opciones);

        $this->submitNew($client, [
            'animal' => (string) $fixture['animalOtraFinca']->getId(),
            'vacuna' => (string) $fixture['vacuna']->getId(),
            'fecha' => '2026-03-01',
            'dosis' => '5 ml',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->vacunaciones($client, $fixture['animalOtraFinca']));

        $client->request('GET', sprintf('/animals/%d/vaccinations', $fixture['animalOtraFinca']->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testHistoricRecordKeepsItsVaccineAfterItIsDeactivated(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $vacunacion = new Vacunacion($fixture['animal'], $fixture['vacuna'], new DateTimeImmutable('2026-03-01'), '5 ml');
        $this->entityManager($client)->persist($vacunacion);
        $this->entityManager($client)->flush();
        $fixture['vacuna']->setActiva(false);
        $this->entityManager($client)->flush();

        $client->request('GET', sprintf('/animals/%d/vaccinations', $fixture['animal']->getId()));
        self::assertStringContainsString($fixture['vacuna']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertStringContainsString('(inactiva)', (string) $client->getResponse()->getContent());

        $crawler = $client->request('GET', sprintf('/vaccinations/%d/edit', $vacunacion->getId()));
        $client->submit($crawler->selectButton('Guardar cambios')->form(['vacunacion[dosis]' => '6 ml']));

        self::assertResponseRedirects(sprintf('/animals/%d/vaccinations', $fixture['animal']->getId()));
        $editada = $this->vacunaciones($client, $fixture['animal'])[0];
        self::assertSame($fixture['vacuna']->getId(), $editada->getVacuna()->getId());
        self::assertSame('6 ml', $editada->getDosis());
        self::assertSame($fixture['administrador']->getId(), $editada->getModificadoPor()?->getId());
    }

    public function testAnEditCannotSwitchToAnotherInactiveVaccine(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $vacunacion = new Vacunacion($fixture['animal'], $fixture['vacuna'], new DateTimeImmutable('2026-03-01'), '5 ml');
        $this->entityManager($client)->persist($vacunacion);
        $this->entityManager($client)->flush();

        $crawler = $client->request('GET', sprintf('/vaccinations/%d/edit', $vacunacion->getId()));
        $client->request('POST', sprintf('/vaccinations/%d/edit', $vacunacion->getId()), ['vacunacion' => [
            'vacuna' => (string) $fixture['vacunaInactiva']->getId(),
            'fecha' => '2026-03-01',
            'dosis' => '5 ml',
            '_token' => $crawler->filterXPath('//input[@name="vacunacion[_token]"]')->attr('value'),
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($fixture['vacuna']->getId(), $this->vacunaciones($client, $fixture['animal'])[0]->getVacuna()->getId());
    }

    public function testHistoryIsChronological(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);
        foreach (['2026-04-01' => 'Tercera', '2026-02-01' => 'Primera', '2026-03-01' => 'Segunda'] as $fecha => $nota) {
            $this->entityManager($client)->persist(
                (new Vacunacion($fixture['animal'], $fixture['vacuna'], new DateTimeImmutable($fecha), '5 ml'))->setObservaciones($nota),
            );
        }
        $this->entityManager($client)->flush();

        $crawler = $client->request('GET', sprintf('/animals/%d/vaccinations', $fixture['animal']->getId()));

        $filas = $crawler->filterXPath('//tr[@data-vaccination-row]')->each(static fn ($fila) => preg_replace('/\s+/', ' ', trim($fila->text())));
        self::assertCount(3, $filas);
        self::assertStringStartsWith('01/02/2026', $filas[0]);
        self::assertStringContainsString('Primera', $filas[0]);
        self::assertStringStartsWith('01/04/2026', $filas[2]);
    }

    public function testOnlyTheAdministratorDeletesAndInactiveFarmsAreReadOnly(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $vacunacion = new Vacunacion($fixture['animal'], $fixture['vacuna'], new DateTimeImmutable('2026-03-01'), '5 ml');
        $otra = new Vacunacion($fixture['animal'], $fixture['vacuna'], new DateTimeImmutable('2026-03-15'), '5 ml');
        $this->entityManager($client)->persist($vacunacion);
        $this->entityManager($client)->persist($otra);
        $this->entityManager($client)->flush();
        $historial = sprintf('/animals/%d/vaccinations', $fixture['animal']->getId());

        $client->loginUser($fixture['encargado']);
        $client->request('GET', $historial);
        self::assertStringNotContainsString('Eliminar', (string) $client->getResponse()->getContent());
        $client->request('POST', sprintf('/vaccinations/%d/delete', $vacunacion->getId()), ['_token' => 'any']);
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($fixture['administrador']);
        $crawler = $client->request('GET', $historial);
        $client->submit($crawler->selectButton('Eliminar')->form());
        self::assertResponseRedirects($historial);
        self::assertCount(1, $this->vacunaciones($client, $fixture['animal']));

        $tokenValido = $client->request('GET', $historial)->filterXPath('//form[contains(@action, "/delete")]//input[@name="_token"]')->attr('value');
        $this->entityManager($client)->find(Finca::class, $fixture['finca']->getId())?->setActivo(false);
        $this->entityManager($client)->flush();

        $crawler = $client->request('GET', $historial);
        self::assertStringContainsString('La finca está inactiva; su historial es de solo lectura.', (string) $client->getResponse()->getContent());
        self::assertCount(0, $crawler->filterXPath('//form[contains(@action, "/delete")]'));
        $client->request('POST', sprintf('/vaccinations/%d/delete', $otra->getId()), ['_token' => $tokenValido]);
        self::assertResponseRedirects($historial);
        $client->request('GET', sprintf('/vaccinations/%d/edit', $otra->getId()));
        self::assertResponseRedirects($historial);
        self::assertCount(1, $this->vacunaciones($client, $fixture['animal']));
    }

    public function testListingOnlyShowsVaccinationsInsideTheUserScope(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $this->entityManager($client)->persist(new Vacunacion($fixture['animal'], $fixture['vacuna'], new DateTimeImmutable('2026-03-01'), '5 ml'));
        $this->entityManager($client)->persist(new Vacunacion($fixture['animalOtraFinca'], $fixture['vacuna'], new DateTimeImmutable('2026-03-01'), '5 ml'));
        $this->entityManager($client)->flush();

        $client->loginUser($fixture['encargado']);
        $client->request('GET', '/vaccinations');
        $contenido = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($fixture['animal']->getArete(), $contenido);
        self::assertStringNotContainsString($fixture['animalOtraFinca']->getArete(), $contenido);

        $client->loginUser($fixture['administrador']);
        $client->request('GET', '/vaccinations');
        self::assertStringContainsString($fixture['animalOtraFinca']->getArete(), (string) $client->getResponse()->getContent());
    }

    /** @param array<string, string> $campos */
    private function submitNew(KernelBrowser $client, array $campos): void
    {
        $crawler = $client->request('GET', '/vaccinations/new');
        // Petición directa para poder enviar también opciones que el formulario no ofrece.
        $client->request('POST', '/vaccinations/new', ['vacunacion' => $campos + [
            'observaciones' => '',
            '_token' => $crawler->filterXPath('//input[@name="vacunacion[_token]"]')->attr('value'),
        ]]);
    }

    /** @return list<Vacunacion> */
    private function vacunaciones(KernelBrowser $client, Animal $animal): array
    {
        $this->entityManager($client)->clear();

        return $this->entityManager($client)->getRepository(Vacunacion::class)->findBy(['animal' => $animal->getId()], ['fecha' => 'ASC']);
    }

    /** @return array<string, mixed> */
    private function createFixture(KernelBrowser $client): array
    {
        $entityManager = $this->entityManager($client);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'VAC-'.$suffix);
        $otraOrganizacion = new OrganizacionGanadera('Otra organización '.$suffix, 'VAC-OTRA-'.$suffix);
        $finca = (new Finca('Finca propia '.$suffix, 'VCN-'.$suffix))->setOrganizacion($organizacion);
        $fincaAlterna = (new Finca('Finca alterna '.$suffix, 'VCN-ALT-'.$suffix))->setOrganizacion($organizacion);
        $administrador = (new Usuario('Administrador', 'vac-admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado', 'vac-manager-'.$suffix.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($finca);
        $vacuna = new Vacuna($organizacion, 'Aftosa '.$suffix);
        $vacunaInactiva = (new Vacuna($organizacion, 'Retirada '.$suffix))->setActiva(false);
        $vacunaAjena = new Vacuna($otraOrganizacion, 'Ajena '.$suffix);
        $animal = (new Animal($organizacion, $finca, 'AN-V-'.$suffix, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO))
            ->setArete('VAC-'.$suffix)
            ->setFechaNacimiento(new DateTimeImmutable('2026-01-10'))
            ->setCreadoPor($administrador);
        $animalOtraFinca = (new Animal($organizacion, $fincaAlterna, 'AN-VA-'.$suffix, Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO))
            ->setArete('VAC-ALT-'.$suffix)
            ->setCreadoPor($administrador);

        foreach ([$organizacion, $otraOrganizacion, $finca, $fincaAlterna, $administrador, $encargado, $vacuna, $vacunaInactiva, $vacunaAjena, $animal, $animalOtraFinca] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('organizacion', 'finca', 'administrador', 'encargado', 'vacuna', 'vacunaInactiva', 'vacunaAjena', 'animal', 'animalOtraFinca');
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
