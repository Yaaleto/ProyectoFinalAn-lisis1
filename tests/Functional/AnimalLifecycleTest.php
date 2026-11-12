<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\MontaReproductiva;
use App\Entity\OrganizacionGanadera;
use App\Entity\Pesaje;
use App\Entity\ProduccionLeche;
use App\Entity\Usuario;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AnimalLifecycleTest extends WebTestCase
{
    public function testFarmManagerRecordsADatedExitAndTheProfileShowsIt(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);
        $id = $fixture['animal']->getId();

        $this->submitStatus($client, $id, Animal::ESTADO_VENDIDO, '2026-06-01');

        self::assertResponseRedirects(sprintf('/animals/%d', $id));
        $animal = $this->reload($client, $id);
        self::assertSame(Animal::ESTADO_VENDIDO, $animal->getEstado());
        self::assertSame('2026-06-01', $animal->getFechaSalida()?->format('Y-m-d'));
        $client->followRedirect();
        self::assertStringContainsString('01/06/2026', (string) $client->getResponse()->getContent());
    }

    public function testExitWithoutDateOrActiveStateWithDateIsRejected(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $id = $fixture['animal']->getId();

        $this->submitStatus($client, $id, Animal::ESTADO_FALLECIDO, '');
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Indica la fecha de salida.', (string) $client->getResponse()->getContent());

        $this->submitStatus($client, $id, Animal::ESTADO_ACTIVO, '2026-06-01');
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Un animal activo no lleva fecha de salida.', (string) $client->getResponse()->getContent());
        self::assertSame(Animal::ESTADO_ACTIVO, $this->reload($client, $id)->getEstado());
    }

    public function testExitCannotBeFutureOrPrecedeBirthOrTheLastRecord(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $entityManager = $this->entityManager($client);
        $entityManager->persist(new Pesaje($fixture['animal'], new DateTimeImmutable('2026-05-20'), '310.00'));
        $entityManager->flush();
        $id = $fixture['animal']->getId();

        foreach ([
            (new DateTimeImmutable('tomorrow'))->format('Y-m-d') => 'La fecha de salida no puede estar en el futuro.',
            '2026-01-09' => 'La salida no puede preceder al nacimiento.',
            '2026-05-19' => 'La salida no puede ser anterior al último registro operativo (20/05/2026).',
        ] as $fecha => $mensaje) {
            $this->submitStatus($client, $id, Animal::ESTADO_VENDIDO, $fecha);
            self::assertResponseStatusCodeSame(422, $fecha);
            self::assertStringContainsString($mensaje, (string) $client->getResponse()->getContent(), $fecha);
        }

        self::assertSame(Animal::ESTADO_ACTIVO, $this->reload($client, $id)->getEstado());
    }

    public function testAnimalCanBeReactivatedAndLosesItsExitDate(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $fixture['animal']->registrarSalida(Animal::ESTADO_EGRESADO, new DateTimeImmutable('2026-06-01'));
        $this->entityManager($client)->flush();
        $id = $fixture['animal']->getId();

        $this->submitStatus($client, $id, Animal::ESTADO_ACTIVO, '');

        self::assertResponseRedirects(sprintf('/animals/%d', $id));
        $animal = $this->reload($client, $id);
        self::assertSame(Animal::ESTADO_ACTIVO, $animal->getEstado());
        self::assertNull($animal->getFechaSalida());
    }

    public function testFarmManagerCannotChangeTheStateOfAnotherFarmAnimal(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $client->request('GET', sprintf('/animals/%d/status', $fixture['animalOtraFinca']->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorDeletesAnAnimalWithoutRecordsOrOffspring(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $id = $fixture['animal']->getId();

        $this->deleteFromProfile($client, $id);

        self::assertResponseRedirects('/animals');
        $this->entityManager($client)->clear();
        self::assertNull($this->entityManager($client)->find(Animal::class, $id));
    }

    public function testAnimalWithOperationalRecordsCannotBeDeleted(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $entityManager = $this->entityManager($client);
        $entityManager->persist(new Pesaje($fixture['animal'], new DateTimeImmutable('2026-05-20'), '310.00'));
        $entityManager->flush();
        $id = $fixture['animal']->getId();

        $this->deleteFromProfile($client, $id);

        self::assertResponseRedirects(sprintf('/animals/%d', $id));
        $client->followRedirect();
        self::assertStringContainsString('No se puede eliminar el animal porque tiene registros operativos.', (string) $client->getResponse()->getContent());
        self::assertNotNull($this->reload($client, $id));
    }

    public function testAnimalWithOffspringCannotBeDeleted(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $cria = (new Animal($fixture['organizacion'], $fixture['finca'], 'AN-CRIA-'.$fixture['suffix'], Animal::SEXO_MACHO, Animal::ORIGEN_NACIDO_EN_FINCA, new DateTimeImmutable('2025-01-01')))
            ->setArete('CRIA-'.$fixture['suffix'])
            ->setMadre($fixture['animal']);
        $this->entityManager($client)->persist($cria);
        $this->entityManager($client)->flush();
        $id = $fixture['animal']->getId();

        $this->deleteFromProfile($client, $id);

        self::assertResponseRedirects(sprintf('/animals/%d', $id));
        $client->followRedirect();
        self::assertStringContainsString('está registrado como progenitor de otros animales', (string) $client->getResponse()->getContent());
        self::assertNotNull($this->reload($client, $id));
    }

    public function testFarmManagerCannotDeleteAnimals(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);
        $id = $fixture['animal']->getId();

        $client->request('GET', sprintf('/animals/%d', $id));
        self::assertStringNotContainsString('Eliminar', (string) $client->getResponse()->getContent());

        $client->request('POST', sprintf('/animals/%d/delete', $id), ['_token' => 'any']);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->reload($client, $id));
    }

    public function testDeletionWithAnInvalidTokenKeepsTheAnimal(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $id = $fixture['animal']->getId();

        $client->request('POST', sprintf('/animals/%d/delete', $id), ['_token' => 'invalid']);

        self::assertResponseRedirects(sprintf('/animals/%d', $id));
        self::assertNotNull($this->reload($client, $id));
    }

    public function testBirthDateCannotBeMovedAfterExistingRecords(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $this->entityManager($client)->persist(new Pesaje($fixture['animal'], new DateTimeImmutable('2026-05-20'), '310.00'));
        $this->entityManager($client)->flush();
        $id = $fixture['animal']->getId();

        $crawler = $client->request('GET', sprintf('/animals/%d/edit', $id));
        $client->submit($crawler->selectButton('Guardar cambios')->form(['animal[fechaNacimiento]' => '2026-05-21', 'animal[fechaIngreso]' => '2026-05-21']));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('La fecha de nacimiento no puede ser posterior al primer registro operativo del animal (20/05/2026).', (string) $client->getResponse()->getContent());
        self::assertSame('2026-01-10', $this->reload($client, $id)?->getFechaNacimiento()?->format('Y-m-d'));

        $crawler = $client->request('GET', sprintf('/animals/%d/edit', $id));
        $client->submit($crawler->selectButton('Guardar cambios')->form(['animal[fechaNacimiento]' => '2026-05-20', 'animal[fechaIngreso]' => '2026-05-20']));
        self::assertResponseRedirects('/animals');
        self::assertSame('2026-05-20', $this->reload($client, $id)?->getFechaNacimiento()?->format('Y-m-d'));
    }

    public function testSexCannotChangeWhileRecordsDependOnIt(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $entityManager = $this->entityManager($client);
        $macho = $entityManager->find(Animal::class, $fixture['animalOtraFinca']->getId());
        $entityManager->persist(new MontaReproductiva($fixture['animal'], $macho, new DateTimeImmutable('2026-03-01')));
        $entityManager->flush();

        foreach ([
            [$fixture['animal']->getId(), Animal::SEXO_MACHO, 'No puedes cambiar el sexo de una hembra con montas o producción de leche registradas.'],
            [$macho?->getId(), Animal::SEXO_HEMBRA, 'No puedes cambiar el sexo de un macho con montas registradas.'],
        ] as [$id, $sexoNuevo, $mensaje]) {
            $sexoAnterior = $this->reload($client, $id)?->getSexo();
            $crawler = $client->request('GET', sprintf('/animals/%d/edit', $id));
            $form = $crawler->selectButton('Guardar cambios')->form(['animal[sexo]' => $sexoNuevo]);
            if (isset($form['animal[productoraLeche]'])) {
                $form['animal[productoraLeche]']->untick();
            }
            $client->submit($form);
            self::assertResponseStatusCodeSame(422, $mensaje);
            self::assertStringContainsString($mensaje, (string) $client->getResponse()->getContent());
            self::assertSame($sexoAnterior, $this->reload($client, $id)?->getSexo());
        }
    }

    public function testAProducingFemaleWithMilkingsKeepsItsSexButCanStopProducing(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $entityManager = $this->entityManager($client);
        $vaca = $entityManager->find(Animal::class, $fixture['animal']->getId())?->setProductoraLeche(true);
        $entityManager->flush();
        $entityManager->persist(new ProduccionLeche($vaca, $vaca->getFinca(), new DateTimeImmutable('2026-03-01 05:30'), '10.00'));
        $entityManager->flush();
        $id = $fixture['animal']->getId();

        $crawler = $client->request('GET', sprintf('/animals/%d/edit', $id));
        $form = $crawler->selectButton('Guardar cambios')->form(['animal[sexo]' => Animal::SEXO_MACHO]);
        $form['animal[productoraLeche]']->untick();
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(Animal::SEXO_HEMBRA, $this->reload($client, $id)?->getSexo());

        $crawler = $client->request('GET', sprintf('/animals/%d/edit', $id));
        $form = $crawler->selectButton('Guardar cambios')->form();
        $form['animal[productoraLeche]']->untick();
        $client->submit($form);
        self::assertResponseRedirects('/animals');
        self::assertFalse($this->reload($client, $id)?->isProductoraLeche());
    }

    public function testSexCanChangeWhenOnlyWeightsExist(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $this->entityManager($client)->persist(new Pesaje($fixture['animal'], new DateTimeImmutable('2026-03-01'), '310.00'));
        $this->entityManager($client)->flush();
        $id = $fixture['animal']->getId();

        $crawler = $client->request('GET', sprintf('/animals/%d/edit', $id));
        $client->submit($crawler->selectButton('Guardar cambios')->form(['animal[sexo]' => Animal::SEXO_MACHO]));

        self::assertResponseRedirects('/animals');
        self::assertSame(Animal::SEXO_MACHO, $this->reload($client, $id)?->getSexo());
    }

    public function testEarTagIsRequiredWhenRegisteringAnAnimal(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/animals/new');
        $client->submit($crawler->selectButton('Registrar ingreso')->form([
            'animal[arete]' => '',
            'animal[finca]' => (string) $fixture['finca']->getId(),
            'animal[sexo]' => Animal::SEXO_HEMBRA,
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('El arete es obligatorio.', (string) $client->getResponse()->getContent());
    }

    private function submitStatus(KernelBrowser $client, int $id, string $estado, string $fecha): void
    {
        $crawler = $client->request('GET', sprintf('/animals/%d/status', $id));
        $client->submit($crawler->selectButton('Guardar estado')->form([
            'salida_animal[estado]' => $estado,
            'salida_animal[fechaSalida]' => $fecha,
        ]));
    }

    private function deleteFromProfile(KernelBrowser $client, int $id): void
    {
        $crawler = $client->request('GET', sprintf('/animals/%d', $id));
        $client->submit($crawler->selectButton('Eliminar')->form());
    }

    private function reload(KernelBrowser $client, int $id): ?Animal
    {
        $this->entityManager($client)->clear();

        return $this->entityManager($client)->find(Animal::class, $id);
    }

    /** @return array{suffix: string, organizacion: OrganizacionGanadera, finca: Finca, administrador: Usuario, encargado: Usuario, animal: Animal, animalOtraFinca: Animal} */
    private function createFixture(KernelBrowser $client): array
    {
        $entityManager = $this->entityManager($client);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'CICLO-'.$suffix);
        $finca = (new Finca('Finca propia '.$suffix, 'CIC-'.$suffix))->setOrganizacion($organizacion);
        $fincaAlterna = (new Finca('Finca alterna '.$suffix, 'CIC-ALT-'.$suffix))->setOrganizacion($organizacion);
        $administrador = (new Usuario('Administrador', 'ciclo-admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado', 'ciclo-manager-'.$suffix.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($finca);
        $animal = (new Animal($organizacion, $finca, 'AN-'.$suffix, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01')))
            ->setArete('CICLO-'.$suffix)
            ->definirFechas(new DateTimeImmutable('2026-01-10'), new DateTimeImmutable('2026-01-10'))
            ->setCreadoPor($administrador);
        $animalOtraFinca = (new Animal($organizacion, $fincaAlterna, 'AN-ALT-'.$suffix, Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01')))
            ->setArete('CICLO-ALT-'.$suffix)
            ->setCreadoPor($administrador);

        foreach ([$organizacion, $finca, $fincaAlterna, $administrador, $encargado, $animal, $animalOtraFinca] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('suffix', 'organizacion', 'finca', 'administrador', 'encargado', 'animal', 'animalOtraFinca');
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
