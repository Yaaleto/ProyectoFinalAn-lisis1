<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\MontaReproductiva;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MatingRecordTest extends WebTestCase
{
    public function testAdministratorRegistersAMatingWithAMaleThroughTheForm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $this->submitNew($client, [
            'hembra' => (string) $fixture['hembra']->getId(),
            'macho' => (string) $fixture['macho']->getId(),
            'fecha' => '2026-03-01',
            'observacion' => 'Monta natural',
        ]);

        self::assertResponseRedirects(sprintf('/animals/%d/matings', $fixture['hembra']->getId()));
        $monta = $this->montas($client, $fixture['hembra'])[0] ?? null;
        self::assertInstanceOf(MontaReproductiva::class, $monta);
        self::assertSame($fixture['macho']->getId(), $monta->getMacho()?->getId());
        self::assertSame('Monta natural', $monta->getObservacion());
        self::assertSame($fixture['organizacion']->getId(), $monta->getOrganizacion()->getId());
        self::assertSame($fixture['administrador']->getId(), $monta->getCreadoPor()?->getId());
    }

    public function testMaleAndObservationAreOptional(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $this->submitNew($client, ['hembra' => (string) $fixture['hembra']->getId(), 'macho' => '', 'fecha' => '2026-03-01']);

        self::assertResponseRedirects();
        $monta = $this->montas($client, $fixture['hembra'])[0];
        self::assertNull($monta->getMacho());
        self::assertNull($monta->getObservacion());
    }

    public function testFemaleAndDateAreRequired(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $this->submitNew($client, ['hembra' => '', 'fecha' => '2026-03-01']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Selecciona la hembra.', (string) $client->getResponse()->getContent());

        $this->submitNew($client, ['hembra' => (string) $fixture['hembra']->getId(), 'fecha' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Indica la fecha de la monta.', (string) $client->getResponse()->getContent());
    }

    public function testWrongSexesAndAnimalsOfAnotherOrganizationAreRejected(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/matings/new');
        $hembras = $crawler->filterXPath('//select[@name="monta_reproductiva[hembra]"]/option')->each(static fn ($option) => $option->attr('value'));
        $machos = $crawler->filterXPath('//select[@name="monta_reproductiva[macho]"]/option')->each(static fn ($option) => $option->attr('value'));
        self::assertNotContains((string) $fixture['macho']->getId(), $hembras);
        self::assertNotContains((string) $fixture['hembraAjena']->getId(), $hembras);
        self::assertNotContains((string) $fixture['hembra']->getId(), $machos);
        self::assertNotContains((string) $fixture['machoAjeno']->getId(), $machos);

        foreach ([
            'hembra que no es hembra' => ['hembra' => $fixture['macho'], 'macho' => null],
            'macho que no es macho' => ['hembra' => $fixture['hembra'], 'macho' => $fixture['otraHembra']],
            'hembra de otra organización' => ['hembra' => $fixture['hembraAjena'], 'macho' => null],
            'macho de otra organización' => ['hembra' => $fixture['hembra'], 'macho' => $fixture['machoAjeno']],
        ] as $caso => $animales) {
            $this->submitNew($client, [
                'hembra' => (string) $animales['hembra']->getId(),
                'macho' => null === $animales['macho'] ? '' : (string) $animales['macho']->getId(),
                'fecha' => '2026-03-01',
            ]);
            self::assertResponseStatusCodeSame(422, $caso);
        }

        self::assertCount(0, $this->montas($client, $fixture['hembra']));
        self::assertCount(0, $this->montas($client, $fixture['hembraAjena']));
    }

    public function testAMaleFromAnotherFarmOfTheOrganizationIsAccepted(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $crawler = $client->request('GET', '/matings/new');
        $machos = $crawler->filterXPath('//select[@name="monta_reproductiva[macho]"]/option')->each(static fn ($option) => $option->attr('value'));
        self::assertContains((string) $fixture['machoOtraFinca']->getId(), $machos);

        $this->submitNew($client, [
            'hembra' => (string) $fixture['hembra']->getId(),
            'macho' => (string) $fixture['machoOtraFinca']->getId(),
            'fecha' => '2026-03-01',
        ]);

        self::assertResponseRedirects();
        self::assertSame($fixture['machoOtraFinca']->getId(), $this->montas($client, $fixture['hembra'])[0]->getMacho()?->getId());
    }

    public function testFarmManagerNeedsAccessToTheFemale(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $this->submitNew($client, ['hembra' => (string) $fixture['hembraOtraFinca']->getId(), 'fecha' => '2026-03-01']);
        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->montas($client, $fixture['hembraOtraFinca']));

        $client->request('GET', sprintf('/animals/%d/matings', $fixture['hembraOtraFinca']->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidDatesAreRejected(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        foreach ([
            '2026-01-09' => 'La fecha del registro no puede ser anterior al nacimiento del animal.',
            (new DateTimeImmutable('tomorrow'))->format('Y-m-d') => 'La fecha de la monta no puede estar en el futuro.',
        ] as $fecha => $mensaje) {
            $this->submitNew($client, ['hembra' => (string) $fixture['hembra']->getId(), 'fecha' => $fecha]);
            self::assertResponseStatusCodeSame(422, $fecha);
            self::assertStringContainsString($mensaje, (string) $client->getResponse()->getContent());
            if (str_contains($mensaje, 'fecha')) {
                // Los errores de fecha aparecen bajo el campo de fecha, no al inicio del formulario.
                $id = $client->getCrawler()->filterXPath('//input[contains(@name, "[fecha")]')->attr('id');
                self::assertStringContainsString($mensaje, $client->getCrawler()->filterXPath(sprintf('//*[starts-with(@id, "%s_error")]', $id))->text(''));
            }
        }
    }

    public function testReproductiveHistoryShowsTheMatingsOfTheFemaleAndTheMale(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        foreach (['2026-04-01' => 'Segunda', '2026-02-01' => 'Primera'] as $fecha => $nota) {
            $this->entityManager($client)->persist(
                (new MontaReproductiva($fixture['hembra'], $fixture['macho'], new DateTimeImmutable($fecha)))->setObservacion($nota),
            );
        }
        $this->entityManager($client)->flush();

        $crawler = $client->request('GET', sprintf('/animals/%d/matings', $fixture['hembra']->getId()));
        $filas = $crawler->filterXPath('//tr[@data-mating-row]')->each(static fn ($fila) => preg_replace('/\s+/', ' ', trim($fila->text())));
        self::assertCount(2, $filas);
        self::assertStringStartsWith('01/02/2026', $filas[0]);
        self::assertStringContainsString('Primera', $filas[0]);
        self::assertStringStartsWith('01/04/2026', $filas[1]);

        $crawler = $client->request('GET', sprintf('/animals/%d/matings', $fixture['macho']->getId()));
        self::assertCount(2, $crawler->filterXPath('//tr[@data-mating-row]'));
    }

    public function testFarmManagerEditsAMatingAndTheEditIsAudited(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $monta = new MontaReproductiva($fixture['hembra'], null, new DateTimeImmutable('2026-03-01'));
        $this->entityManager($client)->persist($monta);
        $this->entityManager($client)->flush();
        $client->loginUser($fixture['encargado']);

        $crawler = $client->request('GET', sprintf('/matings/%d/edit', $monta->getId()));
        $client->submit($crawler->selectButton('Guardar cambios')->form([
            'monta_reproductiva[macho]' => (string) $fixture['macho']->getId(),
            'monta_reproductiva[fecha]' => '2026-03-02',
            'monta_reproductiva[observacion]' => 'Se confirmó el macho',
        ]));

        self::assertResponseRedirects(sprintf('/animals/%d/matings', $fixture['hembra']->getId()));
        $editada = $this->montas($client, $fixture['hembra'])[0];
        self::assertSame($fixture['macho']->getId(), $editada->getMacho()?->getId());
        self::assertSame('2026-03-02', $editada->getFecha()->format('Y-m-d'));
        self::assertSame($fixture['encargado']->getId(), $editada->getModificadoPor()?->getId());
    }

    public function testOnlyTheAdministratorDeletesAndInactiveFarmsAreReadOnly(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $monta = new MontaReproductiva($fixture['hembra'], null, new DateTimeImmutable('2026-03-01'));
        $otra = new MontaReproductiva($fixture['hembra'], null, new DateTimeImmutable('2026-03-15'));
        $this->entityManager($client)->persist($monta);
        $this->entityManager($client)->persist($otra);
        $this->entityManager($client)->flush();
        $historial = sprintf('/animals/%d/matings', $fixture['hembra']->getId());

        $client->loginUser($fixture['encargado']);
        $client->request('GET', $historial);
        self::assertStringNotContainsString('Eliminar', (string) $client->getResponse()->getContent());
        $client->request('POST', sprintf('/matings/%d/delete', $monta->getId()), ['_token' => 'any']);
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($fixture['administrador']);
        $crawler = $client->request('GET', $historial);
        $client->submit($crawler->selectButton('Eliminar')->form());
        self::assertResponseRedirects($historial);
        self::assertCount(1, $this->montas($client, $fixture['hembra']));

        $tokenValido = $client->request('GET', $historial)->filterXPath('//form[contains(@action, "/delete")]//input[@name="_token"]')->attr('value');
        $this->entityManager($client)->find(Finca::class, $fixture['finca']->getId())?->setActivo(false);
        $this->entityManager($client)->flush();

        $crawler = $client->request('GET', $historial);
        self::assertStringContainsString('La finca está inactiva; su historial es de solo lectura.', (string) $client->getResponse()->getContent());
        self::assertCount(0, $crawler->filterXPath('//form[contains(@action, "/delete")]'));
        $client->request('POST', sprintf('/matings/%d/delete', $otra->getId()), ['_token' => $tokenValido]);
        self::assertResponseRedirects($historial);
        $client->request('GET', sprintf('/matings/%d/edit', $otra->getId()));
        self::assertResponseRedirects($historial);
        self::assertCount(1, $this->montas($client, $fixture['hembra']));
    }

    public function testListingOnlyShowsMatingsInsideTheUserScope(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $this->entityManager($client)->persist(new MontaReproductiva($fixture['hembra'], null, new DateTimeImmutable('2026-03-01')));
        $this->entityManager($client)->persist(new MontaReproductiva($fixture['hembraOtraFinca'], null, new DateTimeImmutable('2026-03-01')));
        $this->entityManager($client)->flush();

        $client->loginUser($fixture['encargado']);
        $client->request('GET', '/matings');
        $contenido = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($fixture['hembra']->getArete(), $contenido);
        self::assertStringNotContainsString($fixture['hembraOtraFinca']->getArete(), $contenido);

        $client->loginUser($fixture['administrador']);
        $client->request('GET', '/matings');
        self::assertStringContainsString($fixture['hembraOtraFinca']->getArete(), (string) $client->getResponse()->getContent());
    }

    /** @param array<string, string|null> $campos */
    private function submitNew(KernelBrowser $client, array $campos): void
    {
        $crawler = $client->request('GET', '/matings/new');
        // Petición directa para poder enviar también animales que el formulario no ofrece.
        $client->request('POST', '/matings/new', ['monta_reproductiva' => $campos + [
            'macho' => '',
            'observacion' => '',
            '_token' => $crawler->filterXPath('//input[@name="monta_reproductiva[_token]"]')->attr('value'),
        ]]);
    }

    /** @return list<MontaReproductiva> */
    private function montas(KernelBrowser $client, Animal $hembra): array
    {
        $this->entityManager($client)->clear();

        return $this->entityManager($client)->getRepository(MontaReproductiva::class)->findBy(['hembra' => $hembra->getId()], ['fecha' => 'ASC']);
    }

    /** @return array<string, mixed> */
    private function createFixture(KernelBrowser $client): array
    {
        $entityManager = $this->entityManager($client);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'MON-'.$suffix);
        $otraOrganizacion = new OrganizacionGanadera('Otra organización '.$suffix, 'MON-OTRA-'.$suffix);
        $finca = (new Finca('Finca propia '.$suffix, 'MNT-'.$suffix))->setOrganizacion($organizacion);
        $fincaAlterna = (new Finca('Finca alterna '.$suffix, 'MNT-ALT-'.$suffix))->setOrganizacion($organizacion);
        $fincaAjena = (new Finca('Finca ajena '.$suffix, 'MNT-AJ-'.$suffix))->setOrganizacion($otraOrganizacion);
        $administrador = (new Usuario('Administrador', 'mon-admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $administradorAjeno = (new Usuario('Administrador ajeno', 'mon-otro-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($otraOrganizacion);
        $encargado = (new Usuario('Encargado', 'mon-manager-'.$suffix.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($finca);
        $nuevo = static fn (OrganizacionGanadera $org, Finca $f, string $clave, string $sexo, Usuario $creador): Animal => (new Animal($org, $f, 'AN-'.$clave.'-'.$suffix, $sexo, Animal::ORIGEN_EXTERNO))
            ->setArete($clave.'-'.$suffix)
            ->setFechaNacimiento(new DateTimeImmutable('2026-01-10'))
            ->setCreadoPor($creador);
        $hembra = $nuevo($organizacion, $finca, 'HEMBRA', Animal::SEXO_HEMBRA, $administrador);
        $otraHembra = $nuevo($organizacion, $finca, 'HEMBRA2', Animal::SEXO_HEMBRA, $administrador);
        $macho = $nuevo($organizacion, $finca, 'MACHO', Animal::SEXO_MACHO, $administrador);
        $hembraOtraFinca = $nuevo($organizacion, $fincaAlterna, 'HEMBRAALT', Animal::SEXO_HEMBRA, $administrador);
        $machoOtraFinca = $nuevo($organizacion, $fincaAlterna, 'MACHOALT', Animal::SEXO_MACHO, $administrador);
        $hembraAjena = $nuevo($otraOrganizacion, $fincaAjena, 'HEMBRAAJ', Animal::SEXO_HEMBRA, $administradorAjeno);
        $machoAjeno = $nuevo($otraOrganizacion, $fincaAjena, 'MACHOAJ', Animal::SEXO_MACHO, $administradorAjeno);

        foreach ([$organizacion, $otraOrganizacion, $finca, $fincaAlterna, $fincaAjena, $administrador, $administradorAjeno, $encargado, $hembra, $otraHembra, $macho, $hembraOtraFinca, $machoOtraFinca, $hembraAjena, $machoAjeno] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('organizacion', 'finca', 'administrador', 'encargado', 'hembra', 'otraHembra', 'macho', 'hembraOtraFinca', 'machoOtraFinca', 'hembraAjena', 'machoAjeno');
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
