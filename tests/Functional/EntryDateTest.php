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

/** RN-19: la fecha de ingreso es el límite inferior de los registros, aunque no se conozca el nacimiento. */
final class EntryDateTest extends WebTestCase
{
    public function testIntakeAsksForTheEntryDateInsteadOfTheBirth(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/animals/new');
        self::assertSame((new DateTimeImmutable('today'))->format('Y-m-d'), $crawler->filterXPath('//input[@name="animal[fechaIngreso]"]')->attr('value'));
        self::assertCount(0, $crawler->filterXPath('//*[@name="animal[fechaNacimiento]" or @name="animal[pesoNacimientoKg]"]'));

        $client->submit($crawler->selectButton('Registrar ingreso')->form($this->datosIngreso($fixture, 'FUT', (new DateTimeImmutable('tomorrow'))->format('Y-m-d'))));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('La fecha de ingreso no puede estar en el futuro.', $this->errorDe($client, 'animal_fechaIngreso'));

        $crawler = $client->request('GET', '/animals/new');
        $client->submit($crawler->selectButton('Registrar ingreso')->form($this->datosIngreso($fixture, 'ING', '2026-03-01')));
        self::assertResponseRedirects('/animals');
        $animal = $this->buscar($client, 'ING-'.$fixture['sufijo']);
        self::assertSame('2026-03-01', $animal->getFechaIngreso()->format('Y-m-d'));
        self::assertNull($animal->getFechaNacimiento());
        self::assertNull($animal->getPesoNacimientoKg());

        $client->request('GET', sprintf('/animals/%d', $animal->getId()));
        self::assertStringContainsString('Fecha de ingreso', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('01/03/2026', (string) $client->getResponse()->getContent());
    }

    public function testBirthProposesTodayAndUsesTheBirthAsTheEntryDate(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/animals/new/birth');
        self::assertSame((new DateTimeImmutable('today'))->format('Y-m-d'), $crawler->filterXPath('//input[@name="animal[fechaNacimiento]"]')->attr('value'));
        self::assertCount(1, $crawler->filterXPath('//input[@name="animal[pesoNacimientoKg]"]'));
        self::assertCount(0, $crawler->filterXPath('//input[@name="animal[fechaIngreso]"]'));

        $client->submit($crawler->selectButton('Registrar nacimiento')->form([
            'animal[arete]' => 'NAC-'.$fixture['sufijo'],
            'animal[finca]' => (string) $fixture['finca']->getId(),
            'animal[sexo]' => Animal::SEXO_MACHO,
            'animal[fechaNacimiento]' => '2026-02-10',
            'animal[pesoNacimientoKg]' => '32.00',
        ]));
        self::assertResponseRedirects('/animals');
        $cria = $this->buscar($client, 'NAC-'.$fixture['sufijo']);
        self::assertSame('2026-02-10', $cria->getFechaNacimiento()?->format('Y-m-d'));
        self::assertSame('2026-02-10', $cria->getFechaIngreso()->format('Y-m-d'));
        self::assertNull($cria->getMadre());

        $crawler = $client->request('GET', sprintf('/animals/%d/edit', $cria->getId()));
        self::assertCount(0, $crawler->filterXPath('//input[@name="animal[fechaIngreso]"]'));
    }

    public function testRecordsBeforeTheEntryDateAreRejectedUnderTheDateField(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $animal = $fixture['animal'];

        foreach (['2026-02-28' => 422, '2026-03-01' => 302] as $fecha => $codigo) {
            $crawler = $client->request('GET', '/weights/new');
            $client->submit($crawler->selectButton('Registrar pesaje')->form([
                'pesaje[animal]' => (string) $animal->getId(),
                'pesaje[fecha]' => $fecha,
                'pesaje[pesoKg]' => '250.00',
            ]));
            self::assertResponseStatusCodeSame($codigo, $fecha);
        }
        $client->request('GET', '/weights/new');
        $crawler = $client->getCrawler();
        $client->submit($crawler->selectButton('Registrar pesaje')->form([
            'pesaje[animal]' => (string) $animal->getId(),
            'pesaje[fecha]' => '2026-01-15',
            'pesaje[pesoKg]' => '250.00',
        ]));
        self::assertStringContainsString('La fecha del registro no puede ser anterior al ingreso del animal (01/03/2026).', $this->errorDe($client, 'pesaje_fecha'));

        $crawler = $client->request('GET', sprintf('/animals/%d/status', $animal->getId()));
        $client->submit($crawler->selectButton('Guardar estado')->form([
            'salida_animal[estado]' => Animal::ESTADO_VENDIDO,
            'salida_animal[fechaSalida]' => '2026-02-15',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('La salida no puede ser anterior al ingreso del animal (01/03/2026).', $this->errorDe($client, 'salida_animal_fechaSalida'));
    }

    public function testEditingKeepsTheBirthAndEntryDatesConsistent(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $animal = $fixture['animal'];
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->persist(new Pesaje($em->find(Animal::class, $animal->getId()), new DateTimeImmutable('2026-03-10'), '300.00'));
        $em->flush();
        $ruta = sprintf('/animals/%d/edit', $animal->getId());

        $crawler = $client->request('GET', $ruta);
        $client->submit($crawler->selectButton('Guardar cambios')->form(['animal[fechaNacimiento]' => '2026-03-05']));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('La fecha de nacimiento no puede ser posterior a la fecha de ingreso (01/03/2026).', $this->errorDe($client, 'animal_fechaNacimiento'));

        $crawler = $client->request('GET', $ruta);
        $client->submit($crawler->selectButton('Guardar cambios')->form(['animal[fechaIngreso]' => '2026-03-11']));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('La fecha de ingreso no puede ser posterior al primer registro operativo del animal (10/03/2026).', $this->errorDe($client, 'animal_fechaIngreso'));

        $crawler = $client->request('GET', $ruta);
        $client->submit($crawler->selectButton('Guardar cambios')->form([
            'animal[fechaNacimiento]' => '2025-06-01',
            'animal[pesoNacimientoKg]' => '30.00',
            'animal[fechaIngreso]' => '2026-03-02',
        ]));
        self::assertResponseRedirects('/animals');
        $editado = $this->buscar($client, $animal->getArete());
        self::assertSame('2025-06-01', $editado->getFechaNacimiento()?->format('Y-m-d'));
        self::assertSame('2026-03-02', $editado->getFechaIngreso()->format('Y-m-d'));
        self::assertSame('30.00', $editado->getPesoNacimientoKg());
    }

    /** @return array<string, string> */
    private function datosIngreso(array $fixture, string $prefijo, string $fechaIngreso): array
    {
        return [
            'animal[arete]' => $prefijo.'-'.$fixture['sufijo'],
            'animal[finca]' => (string) $fixture['finca']->getId(),
            'animal[sexo]' => Animal::SEXO_HEMBRA,
            'animal[fechaIngreso]' => $fechaIngreso,
        ];
    }

    private function errorDe(KernelBrowser $client, string $id): string
    {
        return $client->getCrawler()->filterXPath(sprintf('//*[starts-with(@id, "%s_error")]', $id))->text('');
    }

    private function buscar(KernelBrowser $client, ?string $arete): Animal
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $animal = $em->getRepository(Animal::class)->findOneBy(['arete' => $arete]);
        self::assertInstanceOf(Animal::class, $animal);

        return $animal;
    }

    /** @return array{sufijo: string, finca: Finca, administrador: Usuario, animal: Animal} */
    private function createFixture(KernelBrowser $client): array
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Ganadería '.$sufijo, 'ING-'.$sufijo);
        $finca = (new Finca('Finca '.$sufijo, 'ING-'.$sufijo))->setOrganizacion($organizacion);
        $administrador = (new Usuario('Administrador', 'ing-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))->setOrganizacion($organizacion);
        $animal = (new Animal($organizacion, $finca, 'AN-ING-'.$sufijo, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2026-03-01')))
            ->setArete('EXT-'.$sufijo)
            ->setCreadoPor($administrador);
        foreach ([$organizacion, $finca, $administrador, $animal] as $entidad) {
            $em->persist($entidad);
        }
        $em->flush();

        return ['sufijo' => $sufijo, 'finca' => $finca, 'administrador' => $administrador, 'animal' => $animal];
    }
}
