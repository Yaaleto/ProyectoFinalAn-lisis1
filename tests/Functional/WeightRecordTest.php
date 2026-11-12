<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Pesaje;
use App\Entity\Usuario;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WeightRecordTest extends WebTestCase
{
    public function testAdministratorRegistersAWeightThroughTheForm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $this->submitNew($client, $fixture['animal'], '2026-03-01', '250.50');

        self::assertResponseRedirects(sprintf('/animals/%d/weights', $fixture['animal']->getId()));
        $pesaje = $this->pesajes($client, $fixture['animal'])[0] ?? null;
        self::assertInstanceOf(Pesaje::class, $pesaje);
        self::assertSame('250.50', $pesaje->getPesoKg());
        self::assertSame($fixture['organizacion']->getId(), $pesaje->getOrganizacion()->getId());
        self::assertSame($fixture['administrador']->getId(), $pesaje->getCreadoPor()?->getId());
        self::assertNull($pesaje->getModificadoPor());
    }

    public function testHistoryIsChronologicalAndShowsTheVariation(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);
        foreach (['2026-04-01' => '265.00', '2026-02-01' => '250.00', '2026-03-01' => '260.00'] as $fecha => $peso) {
            $this->entityManager($client)->persist(new Pesaje($fixture['animal'], new DateTimeImmutable($fecha), $peso));
        }
        $this->entityManager($client)->flush();

        $crawler = $client->request('GET', sprintf('/animals/%d/weights', $fixture['animal']->getId()));

        self::assertResponseIsSuccessful();
        $filas = $crawler->filterXPath('//tr[@data-weight-row]')->each(static fn ($fila) => preg_replace('/\s+/', ' ', trim($fila->text())));
        self::assertCount(3, $filas);
        self::assertStringStartsWith('01/02/2026 250.00 kg Primer pesaje', $filas[0]);
        self::assertStringStartsWith('01/03/2026 260.00 kg +10.00 kg', $filas[1]);
        self::assertStringStartsWith('01/04/2026 265.00 kg +5.00 kg', $filas[2]);
    }

    public function testSecondWeightOnTheSameDateAsksToEditTheExistingOne(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $this->entityManager($client)->persist(new Pesaje($fixture['animal'], new DateTimeImmutable('2026-03-01'), '250.00'));
        $this->entityManager($client)->flush();

        $this->submitNew($client, $fixture['animal'], '2026-03-01', '251.00');

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Ya existe un pesaje de este animal en esa fecha. Modifica el registro existente.', (string) $client->getResponse()->getContent());
        self::assertCount(1, $this->pesajes($client, $fixture['animal']));
    }

    public function testInvalidDatesAndWeightsAreRejected(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        foreach ([
            ['2026-01-09', '250.00', 'La fecha del registro no puede ser anterior al nacimiento del animal.'],
            [(new DateTimeImmutable('tomorrow'))->format('Y-m-d'), '250.00', 'La fecha del pesaje no puede estar en el futuro.'],
            ['2026-03-01', '0', 'El peso debe ser mayor que cero.'],
            ['2026-03-01', '-5', 'El peso debe ser mayor que cero.'],
        ] as [$fecha, $peso, $mensaje]) {
            $this->submitNew($client, $fixture['animal'], $fecha, $peso);
            self::assertResponseStatusCodeSame(422, $mensaje);
            self::assertStringContainsString($mensaje, (string) $client->getResponse()->getContent());
            if (str_contains($mensaje, 'fecha')) {
                // Los errores de fecha aparecen bajo el campo de fecha, no al inicio del formulario.
                $id = $client->getCrawler()->filterXPath('//input[contains(@name, "[fecha")]')->attr('id');
                self::assertStringContainsString($mensaje, $client->getCrawler()->filterXPath(sprintf('//*[starts-with(@id, "%s_error")]', $id))->text(''));
            }
        }

        self::assertCount(0, $this->pesajes($client, $fixture['animal']));
    }

    public function testWeightAfterTheAnimalExitIsRejected(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $fixture['animal']->registrarSalida(Animal::ESTADO_VENDIDO, new DateTimeImmutable('2026-05-01'));
        $this->entityManager($client)->flush();

        $this->submitNew($client, $fixture['animal'], '2026-05-02', '300.00');

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('La fecha del registro no puede ser posterior a la salida del animal.', (string) $client->getResponse()->getContent());
    }

    public function testFarmManagerOnlyWeighsAnimalsOfItsFarm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $crawler = $client->request('GET', '/weights/new');
        $opciones = $crawler->filterXPath('//select[@name="pesaje[animal]"]/option')->each(static fn ($option) => $option->attr('value'));
        self::assertContains((string) $fixture['animal']->getId(), $opciones);
        self::assertNotContains((string) $fixture['animalOtraFinca']->getId(), $opciones);
        self::assertNotContains((string) $fixture['animalAjeno']->getId(), $opciones);

        $this->submitNew($client, $fixture['animalOtraFinca'], '2026-03-01', '250.00', usarFormulario: false);
        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->pesajes($client, $fixture['animalOtraFinca']));

        $client->request('GET', sprintf('/animals/%d/weights', $fixture['animalOtraFinca']->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnimalsOfAnotherOrganizationOrAnInactiveFarmCannotBeWeighed(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $fixture['fincaAlterna']->setActivo(false);
        $client->loginUser($fixture['administrador']);
        $this->entityManager($client)->flush();

        $crawler = $client->request('GET', '/weights/new');
        $opciones = $crawler->filterXPath('//select[@name="pesaje[animal]"]/option')->each(static fn ($option) => $option->attr('value'));
        self::assertNotContains((string) $fixture['animalOtraFinca']->getId(), $opciones);
        self::assertNotContains((string) $fixture['animalAjeno']->getId(), $opciones);

        foreach (['animalAjeno', 'animalOtraFinca'] as $clave) {
            $this->submitNew($client, $fixture[$clave], '2026-03-01', '250.00', usarFormulario: false);
            self::assertResponseStatusCodeSame(422, $clave);
            self::assertCount(0, $this->pesajes($client, $fixture[$clave]), $clave);
        }
    }

    public function testFarmManagerEditsAWeightAndTheEditIsAudited(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $pesaje = new Pesaje($fixture['animal'], new DateTimeImmutable('2026-03-01'), '250.00');
        $this->entityManager($client)->persist($pesaje);
        $this->entityManager($client)->flush();
        $client->loginUser($fixture['encargado']);

        $crawler = $client->request('GET', sprintf('/weights/%d/edit', $pesaje->getId()));
        $client->submit($crawler->selectButton('Guardar cambios')->form([
            'pesaje[fecha]' => '2026-03-02',
            'pesaje[pesoKg]' => '255.75',
        ]));

        self::assertResponseRedirects(sprintf('/animals/%d/weights', $fixture['animal']->getId()));
        $editado = $this->pesajes($client, $fixture['animal'])[0];
        self::assertSame('2026-03-02', $editado->getFecha()->format('Y-m-d'));
        self::assertSame('255.75', $editado->getPesoKg());
        self::assertSame($fixture['administrador']->getId(), $editado->getCreadoPor()?->getId());
        self::assertSame($fixture['encargado']->getId(), $editado->getModificadoPor()?->getId());
    }

    public function testOnlyTheAdministratorDeletesWeights(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $pesaje = new Pesaje($fixture['animal'], new DateTimeImmutable('2026-03-01'), '250.00');
        $this->entityManager($client)->persist($pesaje);
        $this->entityManager($client)->flush();
        $historial = sprintf('/animals/%d/weights', $fixture['animal']->getId());

        $client->loginUser($fixture['encargado']);
        $client->request('GET', $historial);
        self::assertStringNotContainsString('Eliminar', (string) $client->getResponse()->getContent());
        $client->request('POST', sprintf('/weights/%d/delete', $pesaje->getId()), ['_token' => 'any']);
        self::assertResponseStatusCodeSame(403);
        self::assertCount(1, $this->pesajes($client, $fixture['animal']));

        $client->loginUser($fixture['administrador']);
        $crawler = $client->request('GET', $historial);
        $client->submit($crawler->selectButton('Eliminar')->form());
        self::assertResponseRedirects($historial);
        self::assertCount(0, $this->pesajes($client, $fixture['animal']));
    }

    public function testWeightsOfAnInactiveFarmAreReadOnly(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $pesaje = new Pesaje($fixture['animal'], new DateTimeImmutable('2026-03-01'), '250.00');
        $this->entityManager($client)->persist($pesaje);
        $this->entityManager($client)->flush();
        $historial = sprintf('/animals/%d/weights', $fixture['animal']->getId());
        // Token válido obtenido mientras la finca seguía activa: el rechazo debe venir de la finca, no del CSRF.
        $tokenValido = $client->request('GET', $historial)->filterXPath('//form[contains(@action, "/delete")]//input[@name="_token"]')->attr('value');
        $finca = $this->entityManager($client)->find(Finca::class, $fixture['finca']->getId());
        $finca?->setActivo(false);
        $this->entityManager($client)->flush();

        $crawler = $client->request('GET', $historial);
        $contenido = (string) $client->getResponse()->getContent();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('250.00 kg', $contenido);
        self::assertStringContainsString('La finca está inactiva; su historial es de solo lectura.', $contenido);
        self::assertCount(0, $crawler->filterXPath(sprintf('//a[contains(@href, "/weights/%d/edit")]', $pesaje->getId())));
        self::assertCount(0, $crawler->filterXPath('//form[contains(@action, "/delete")]'));

        $client->request('GET', sprintf('/weights/%d/edit', $pesaje->getId()));
        self::assertResponseRedirects($historial);

        $client->request('POST', sprintf('/weights/%d/delete', $pesaje->getId()), ['_token' => $tokenValido]);
        self::assertResponseRedirects($historial);
        $client->followRedirect();
        self::assertStringContainsString('La finca del animal está inactiva; su historial de pesajes es de solo lectura.', (string) $client->getResponse()->getContent());
        self::assertCount(1, $this->pesajes($client, $fixture['animal']));
    }

    public function testListingOnlyShowsWeightsInsideTheUserScope(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $this->entityManager($client)->persist(new Pesaje($fixture['animal'], new DateTimeImmutable('2026-03-01'), '250.00'));
        $this->entityManager($client)->persist(new Pesaje($fixture['animalOtraFinca'], new DateTimeImmutable('2026-03-01'), '400.00'));
        $client->loginUser($fixture['administradorAjeno']);
        $this->entityManager($client)->persist(new Pesaje($fixture['animalAjeno'], new DateTimeImmutable('2026-03-01'), '999.00'));
        $this->entityManager($client)->flush();

        $client->loginUser($fixture['encargado']);
        $client->request('GET', '/weights');
        $contenido = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($fixture['animal']->getArete(), $contenido);
        self::assertStringNotContainsString($fixture['animalOtraFinca']->getArete(), $contenido);
        self::assertStringNotContainsString($fixture['animalAjeno']->getArete(), $contenido);

        $client->loginUser($fixture['administrador']);
        $client->request('GET', '/weights');
        $contenido = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($fixture['animalOtraFinca']->getArete(), $contenido);
        self::assertStringNotContainsString($fixture['animalAjeno']->getArete(), $contenido);
    }

    private function submitNew(KernelBrowser $client, Animal $animal, string $fecha, string $peso, bool $usarFormulario = true): void
    {
        $crawler = $client->request('GET', '/weights/new');
        if ($usarFormulario) {
            $client->submit($crawler->selectButton('Registrar pesaje')->form([
                'pesaje[animal]' => (string) $animal->getId(),
                'pesaje[fecha]' => $fecha,
                'pesaje[pesoKg]' => $peso,
            ]));

            return;
        }

        // Petición manual: simula enviar el ID de un animal que el formulario no ofrece.
        $client->request('POST', '/weights/new', ['pesaje' => [
            'animal' => (string) $animal->getId(),
            'fecha' => $fecha,
            'pesoKg' => $peso,
            '_token' => $crawler->filterXPath('//input[@name="pesaje[_token]"]')->attr('value'),
        ]]);
    }

    /** @return list<Pesaje> */
    private function pesajes(KernelBrowser $client, Animal $animal): array
    {
        $this->entityManager($client)->clear();

        return $this->entityManager($client)->getRepository(Pesaje::class)->findBy(['animal' => $animal->getId()], ['fecha' => 'ASC']);
    }

    /** @return array<string, mixed> */
    private function createFixture(KernelBrowser $client): array
    {
        $entityManager = $this->entityManager($client);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'PESO-'.$suffix);
        $otraOrganizacion = new OrganizacionGanadera('Otra organización '.$suffix, 'PESO-OTRA-'.$suffix);
        $finca = (new Finca('Finca propia '.$suffix, 'PES-'.$suffix))->setOrganizacion($organizacion);
        $fincaAlterna = (new Finca('Finca alterna '.$suffix, 'PES-ALT-'.$suffix))->setOrganizacion($organizacion);
        $fincaAjena = (new Finca('Finca ajena '.$suffix, 'PES-AJ-'.$suffix))->setOrganizacion($otraOrganizacion);
        $administrador = (new Usuario('Administrador', 'peso-admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $administradorAjeno = (new Usuario('Administrador ajeno', 'peso-otro-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($otraOrganizacion);
        $encargado = (new Usuario('Encargado', 'peso-manager-'.$suffix.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($finca);
        $animal = (new Animal($organizacion, $finca, 'AN-P-'.$suffix, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01')))
            ->setArete('PESO-'.$suffix)
            ->definirFechas(new DateTimeImmutable('2026-01-10'), new DateTimeImmutable('2026-01-10'))
            ->setCreadoPor($administrador);
        $animalOtraFinca = (new Animal($organizacion, $fincaAlterna, 'AN-PA-'.$suffix, Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01')))
            ->setArete('PESO-ALT-'.$suffix)
            ->setCreadoPor($administrador);
        $animalAjeno = (new Animal($otraOrganizacion, $fincaAjena, 'AN-PJ-'.$suffix, Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01')))
            ->setArete('PESO-AJENO-'.$suffix)
            ->setCreadoPor($administradorAjeno);

        foreach ([$organizacion, $otraOrganizacion, $finca, $fincaAlterna, $fincaAjena, $administrador, $administradorAjeno, $encargado, $animal, $animalOtraFinca, $animalAjeno] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('organizacion', 'finca', 'fincaAlterna', 'administrador', 'administradorAjeno', 'encargado', 'animal', 'animalOtraFinca', 'animalAjeno');
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
