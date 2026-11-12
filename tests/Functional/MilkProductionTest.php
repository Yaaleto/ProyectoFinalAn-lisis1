<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\ProduccionLeche;
use App\Entity\Usuario;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MilkProductionTest extends WebTestCase
{
    public function testTheFormProposesTheCurrentFarmAndStoresTheConfirmedOne(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', sprintf('/milk/new?animal=%d', $fixture['vaca']->getId()));
        $seleccionada = $crawler->filterXPath('//select[@name="produccion_leche[fincaOrdeno]"]/option[@selected]');
        self::assertSame((string) $fixture['finca']->getId(), $seleccionada->attr('value'));

        $client->submit($crawler->selectButton('Registrar ordeño')->form([
            'produccion_leche[fechaHora]' => '2026-03-01T05:30',
            'produccion_leche[cantidadLitros]' => '12.50',
        ]));

        self::assertResponseRedirects(sprintf('/animals/%d/milk', $fixture['vaca']->getId()));
        $ordeno = $this->ordenos($client, $fixture['vaca'])[0] ?? null;
        self::assertInstanceOf(ProduccionLeche::class, $ordeno);
        self::assertSame($fixture['finca']->getId(), $ordeno->getFincaOrdeno()->getId());
        self::assertSame('2026-03-01 05:30', $ordeno->getFechaHora()->format('Y-m-d H:i'));
        self::assertSame('12.50', $ordeno->getCantidadLitros());
        self::assertSame($fixture['organizacion']->getId(), $ordeno->getOrganizacion()->getId());
        self::assertSame($fixture['administrador']->getId(), $ordeno->getCreadoPor()?->getId());
    }

    public function testAHistoricalFarmDifferentFromTheCurrentOneCanBeConfirmed(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $this->submitNew($client, $fixture['vaca'], $fixture['fincaAlterna'], '2026-03-01T05:30', '10.00');

        self::assertResponseRedirects();
        self::assertSame($fixture['fincaAlterna']->getId(), $this->ordenos($client, $fixture['vaca'])[0]->getFincaOrdeno()->getId());
    }

    public function testSeveralMilkingsOnTheSameDayAddUpInTheDailyTotal(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $this->submitNew($client, $fixture['vaca'], $fixture['finca'], '2026-03-01T05:30', '12.50');
        self::assertResponseRedirects();
        $this->submitNew($client, $fixture['vaca'], $fixture['finca'], '2026-03-01T17:00', '10.25');
        self::assertResponseRedirects();
        $this->submitNew($client, $fixture['vaca'], $fixture['finca'], '2026-03-02T05:30', '11.00');
        self::assertResponseRedirects();

        self::assertCount(3, $this->ordenos($client, $fixture['vaca']));
        $crawler = $client->request('GET', sprintf('/animals/%d/milk', $fixture['vaca']->getId()));
        $totales = $crawler->filterXPath('//tr[@data-daily-total]')->each(static fn ($fila) => preg_replace('/\s+/', ' ', trim($fila->text())));
        self::assertSame(['01/03/2026 2 22.75 L', '02/03/2026 1 11.00 L'], $totales);
    }

    public function testOnlyActiveProducingFemalesAreAccepted(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/milk/new');
        $opciones = $crawler->filterXPath('//select[@name="produccion_leche[animal]"]/option')->each(static fn ($option) => $option->attr('value'));
        self::assertContains((string) $fixture['vaca']->getId(), $opciones);

        foreach (['noProductora', 'toro', 'vendida'] as $clave) {
            self::assertNotContains((string) $fixture[$clave]->getId(), $opciones, $clave);
            $this->submitNew($client, $fixture[$clave], $fixture['finca'], '2026-03-01T05:30', '10.00');
            self::assertResponseStatusCodeSame(422, $clave);
            self::assertCount(0, $this->ordenos($client, $fixture[$clave]), $clave);
        }
    }

    public function testFarmManagerCanOnlyChooseItsOwnFarm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $crawler = $client->request('GET', '/milk/new');
        $fincas = $crawler->filterXPath('//select[@name="produccion_leche[fincaOrdeno]"]/option')->each(static fn ($option) => $option->attr('value'));
        self::assertContains((string) $fixture['finca']->getId(), $fincas);
        self::assertNotContains((string) $fixture['fincaAlterna']->getId(), $fincas);

        $this->submitNew($client, $fixture['vaca'], $fixture['fincaAlterna'], '2026-03-01T05:30', '10.00');
        self::assertResponseStatusCodeSame(422);
        $this->submitNew($client, $fixture['vacaOtraFinca'], $fixture['finca'], '2026-03-01T05:30', '10.00');
        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->ordenos($client, $fixture['vaca']));
        self::assertCount(0, $this->ordenos($client, $fixture['vacaOtraFinca']));
    }

    public function testInvalidDatesAndQuantitiesAreRejected(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        foreach ([
            ['2026-01-09T05:30', '10.00', 'La fecha del registro no puede ser anterior al nacimiento del animal.'],
            [(new DateTimeImmutable('+2 hours'))->format('Y-m-d\TH:i'), '10.00', 'La fecha y hora del ordeño no pueden estar en el futuro.'],
            ['2026-03-01T05:30', '0', 'La cantidad de leche debe ser mayor que cero.'],
        ] as [$fechaHora, $litros, $mensaje]) {
            $this->submitNew($client, $fixture['vaca'], $fixture['finca'], $fechaHora, $litros);
            self::assertResponseStatusCodeSame(422, $mensaje);
            self::assertStringContainsString($mensaje, (string) $client->getResponse()->getContent());
            if (str_contains($mensaje, 'fecha')) {
                // Los errores de fecha aparecen bajo el campo de fecha, no al inicio del formulario.
                $id = $client->getCrawler()->filterXPath('//input[contains(@name, "[fecha")]')->attr('id');
                self::assertStringContainsString($mensaje, $client->getCrawler()->filterXPath(sprintf('//*[starts-with(@id, "%s_error")]', $id))->text(''));
            }
        }
        self::assertCount(0, $this->ordenos($client, $fixture['vaca']));
    }

    public function testTotalsAndTheHistoricalFarmDoNotChangeAfterATransfer(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $ordeno = new ProduccionLeche($fixture['vaca'], $fixture['finca'], new DateTimeImmutable('2026-03-01 05:30'), '12.50');
        $this->entityManager($client)->persist($ordeno);
        $this->entityManager($client)->flush();
        $totalesAntes = $this->totalesPorFinca($client, '2026-03-01');

        $vaca = $this->entityManager($client)->find(Animal::class, $fixture['vaca']->getId());
        $vaca?->setFinca($this->entityManager($client)->find(Finca::class, $fixture['fincaAlterna']->getId()));
        $this->entityManager($client)->flush();

        self::assertSame($totalesAntes, $this->totalesPorFinca($client, '2026-03-01'));
        self::assertSame([sprintf('Finca propia %s 1 12.50 L', $fixture['suffix'])], $totalesAntes);

        $crawler = $client->request('GET', sprintf('/milk/%d/edit', $ordeno->getId()));
        $client->submit($crawler->selectButton('Guardar cambios')->form(['produccion_leche[cantidadLitros]' => '13.00']));
        self::assertResponseRedirects();
        $editado = $this->ordenos($client, $fixture['vaca'])[0];
        self::assertSame($fixture['finca']->getId(), $editado->getFincaOrdeno()->getId());
        self::assertSame('13.00', $editado->getCantidadLitros());
        self::assertSame($fixture['administrador']->getId(), $editado->getModificadoPor()?->getId());
    }

    public function testOnlyTheAdministratorDeletesAndInactiveFarmsAreReadOnly(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $ordeno = new ProduccionLeche($fixture['vaca'], $fixture['finca'], new DateTimeImmutable('2026-03-01 05:30'), '12.50');
        $otro = new ProduccionLeche($fixture['vaca'], $fixture['finca'], new DateTimeImmutable('2026-03-01 17:00'), '10.00');
        $this->entityManager($client)->persist($ordeno);
        $this->entityManager($client)->persist($otro);
        $this->entityManager($client)->flush();
        $historial = sprintf('/animals/%d/milk', $fixture['vaca']->getId());

        $client->loginUser($fixture['encargado']);
        $client->request('GET', $historial);
        self::assertStringNotContainsString('Eliminar', (string) $client->getResponse()->getContent());
        $client->request('POST', sprintf('/milk/%d/delete', $ordeno->getId()), ['_token' => 'any']);
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($fixture['administrador']);
        $crawler = $client->request('GET', $historial);
        $client->submit($crawler->selectButton('Eliminar')->form());
        self::assertResponseRedirects($historial);
        self::assertCount(1, $this->ordenos($client, $fixture['vaca']));

        $tokenValido = $client->request('GET', $historial)->filterXPath('//form[contains(@action, "/delete")]//input[@name="_token"]')->attr('value');
        $vaca = $this->entityManager($client)->find(Animal::class, $fixture['vaca']->getId());
        $vaca?->setFinca($this->entityManager($client)->find(Finca::class, $fixture['fincaAlterna']->getId()));
        $this->entityManager($client)->find(Finca::class, $fixture['finca']->getId())?->setActivo(false);
        $this->entityManager($client)->flush();

        $crawler = $client->request('GET', $historial);
        self::assertStringContainsString('Solo lectura', (string) $client->getResponse()->getContent());
        self::assertCount(0, $crawler->filterXPath('//form[contains(@action, "/delete")]'));
        $client->request('POST', sprintf('/milk/%d/delete', $otro->getId()), ['_token' => $tokenValido]);
        self::assertResponseRedirects($historial);
        $client->request('GET', sprintf('/milk/%d/edit', $otro->getId()));
        self::assertResponseRedirects($historial);
        self::assertCount(1, $this->ordenos($client, $fixture['vaca']));
    }

    public function testFarmManagerSeesTheMilkingsThatHappenedOnItsFarm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $this->entityManager($client)->persist(new ProduccionLeche($fixture['vaca'], $fixture['finca'], new DateTimeImmutable('2026-03-01 05:30'), '12.50'));
        $this->entityManager($client)->persist(new ProduccionLeche($fixture['vacaOtraFinca'], $fixture['fincaAlterna'], new DateTimeImmutable('2026-03-01 05:30'), '30.00'));
        $this->entityManager($client)->flush();

        $client->loginUser($fixture['encargado']);
        $client->request('GET', '/milk?fecha=2026-03-01');
        $contenido = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($fixture['vaca']->getArete(), $contenido);
        self::assertStringNotContainsString($fixture['vacaOtraFinca']->getArete(), $contenido);
        self::assertStringNotContainsString('30.00 L', $contenido);

        $client->request('GET', sprintf('/animals/%d/milk', $fixture['vacaOtraFinca']->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    private function submitNew(KernelBrowser $client, Animal $animal, Finca $finca, string $fechaHora, string $litros): void
    {
        $crawler = $client->request('GET', '/milk/new');
        // Petición directa para poder enviar también animales o fincas que el formulario no ofrece.
        $client->request('POST', '/milk/new', ['produccion_leche' => [
            'animal' => (string) $animal->getId(),
            'fincaOrdeno' => (string) $finca->getId(),
            'fechaHora' => $fechaHora,
            'cantidadLitros' => $litros,
            '_token' => $crawler->filterXPath('//input[@name="produccion_leche[_token]"]')->attr('value'),
        ]]);
    }

    /** @return list<string> */
    private function totalesPorFinca(KernelBrowser $client, string $dia): array
    {
        $crawler = $client->request('GET', '/milk?fecha='.$dia);

        return $crawler->filterXPath('//tr[@data-farm-total]')->each(static fn ($fila) => preg_replace('/\s+/', ' ', trim($fila->text())));
    }

    /** @return list<ProduccionLeche> */
    private function ordenos(KernelBrowser $client, Animal $animal): array
    {
        $this->entityManager($client)->clear();

        return $this->entityManager($client)->getRepository(ProduccionLeche::class)->findBy(['animal' => $animal->getId()], ['fechaHora' => 'ASC']);
    }

    /** @return array<string, mixed> */
    private function createFixture(KernelBrowser $client): array
    {
        $entityManager = $this->entityManager($client);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'LEC-'.$suffix);
        $finca = (new Finca('Finca propia '.$suffix, 'LCH-'.$suffix))->setOrganizacion($organizacion);
        $fincaAlterna = (new Finca('Finca alterna '.$suffix, 'LCH-ALT-'.$suffix))->setOrganizacion($organizacion);
        $administrador = (new Usuario('Administrador', 'lec-admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado', 'lec-manager-'.$suffix.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($finca);
        $nuevo = static fn (Finca $f, string $clave, string $sexo): Animal => (new Animal($organizacion, $f, 'AN-'.$clave.'-'.$suffix, $sexo, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01')))
            ->setArete($clave.'-'.$suffix)
            ->definirFechas(new DateTimeImmutable('2026-01-10'), new DateTimeImmutable('2026-01-10'))
            ->setCreadoPor($administrador);
        $vaca = $nuevo($finca, 'VACA', Animal::SEXO_HEMBRA)->setProductoraLeche(true);
        $vacaOtraFinca = $nuevo($fincaAlterna, 'VACAALT', Animal::SEXO_HEMBRA)->setProductoraLeche(true);
        $noProductora = $nuevo($finca, 'NOPROD', Animal::SEXO_HEMBRA);
        $toro = $nuevo($finca, 'TORO', Animal::SEXO_MACHO);
        $vendida = $nuevo($finca, 'VENDIDA', Animal::SEXO_HEMBRA)->setProductoraLeche(true)
            ->registrarSalida(Animal::ESTADO_VENDIDO, new DateTimeImmutable('2026-06-01'));

        foreach ([$organizacion, $finca, $fincaAlterna, $administrador, $encargado, $vaca, $vacaOtraFinca, $noProductora, $toro, $vendida] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('suffix', 'organizacion', 'finca', 'fincaAlterna', 'administrador', 'encargado', 'vaca', 'vacaOtraFinca', 'noProductora', 'toro', 'vendida');
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
