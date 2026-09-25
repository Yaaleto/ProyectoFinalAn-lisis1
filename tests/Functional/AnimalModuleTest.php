<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AnimalModuleTest extends WebTestCase
{
    public function testAdministratorCanOpenAnimalSectionAndOnlySeesItsOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/animals');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filterXPath('//a[@href="/animals" and @aria-current="page"]'));
        self::assertStringContainsString((string) $fixture['animal']->getArete(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($fixture['animal']->getCodigoInterno(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString((string) $fixture['animalAjeno']->getArete(), (string) $client->getResponse()->getContent());
        self::assertCount(1, $crawler->filterXPath(sprintf('//a[@href="/animals/%d" and normalize-space()="Ver"]', $fixture['animal']->getId())));
        self::assertCount(1, $crawler->filterXPath('//a[@href="/animals/new" and normalize-space()="Registrar animal"]'));
        self::assertCount(0, $crawler->filterXPath('//a[@href="/animals/new/birth"]'));
    }

    public function testAdministratorCanOpenAuthorizedAnimalProfile(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', sprintf('/animals/%d', $fixture['animal']->getId()));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString((string) $fixture['animal']->getArete(), (string) $client->getResponse()->getContent());
        self::assertStringContainsString($fixture['finca']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($fixture['animal']->getCodigoInterno(), (string) $client->getResponse()->getContent());
    }

    public function testAdministratorCanRegisterFarmBirthFromTheInterface(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $arete = 'GT-'.strtoupper(bin2hex(random_bytes(3)));

        $crawler = $client->request('GET', '/animals');
        $crawler = $client->click($crawler->selectLink('Registrar animal')->link());
        self::assertCount(1, $crawler->filterXPath('//nav[@aria-labelledby="animal-registration-type-heading"]//a[@aria-current="page" and normalize-space()="Ingreso"]'));
        $crawler = $client->click($crawler->selectLink('Nacimiento')->link());
        self::assertCount(1, $crawler->filterXPath('//nav[@aria-labelledby="animal-registration-type-heading"]//a[@aria-current="page" and normalize-space()="Nacimiento"]'));
        self::assertCount(0, $crawler->filterXPath('//input[@name="animal[codigoInterno]"]'));
        self::assertCount(0, $crawler->filterXPath('//*[@name="animal[origen]" or @name="animal[procedencia]"]'));
        $client->submit($crawler->selectButton('Registrar nacimiento')->form([
            'animal[arete]' => $arete,
            'animal[nombre]' => 'Luna',
            'animal[finca]' => (string) $fixture['finca']->getId(),
            'animal[sexo]' => Animal::SEXO_HEMBRA,
            'animal[fechaNacimiento]' => '2026-01-15',
            'animal[pesoNacimientoKg]' => '35.50',
            'animal[productoraLeche]' => '1',
        ]));

        self::assertResponseRedirects('/animals');
        $animal = $this->entityManager($client)->getRepository(Animal::class)->findOneBy(['arete' => $arete]);
        self::assertInstanceOf(Animal::class, $animal);
        self::assertMatchesRegularExpression('/^AN-[A-F0-9]{12}$/', $animal->getCodigoInterno());
        self::assertSame($fixture['organizacion']->getId(), $animal->getOrganizacion()->getId());
        self::assertSame($fixture['finca']->getId(), $animal->getFinca()->getId());
        self::assertSame(Animal::ORIGEN_NACIDO_EN_FINCA, $animal->getOrigen());
        self::assertSame('2026-01-15', $animal->getFechaNacimiento()?->format('Y-m-d'));
        self::assertNull($animal->getProcedencia());
        self::assertTrue($animal->isProductoraLeche());
    }

    public function testExternalIntakeKeepsOptionalProvenance(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        foreach ([null, 'Finca de procedencia'] as $procedencia) {
            $arete = 'EXT-'.strtoupper(bin2hex(random_bytes(4)));
            $crawler = $client->request('GET', '/animals/new');
            self::assertCount(1, $crawler->filterXPath('//nav[@aria-labelledby="animal-registration-type-heading"]//a[@aria-current="page" and normalize-space()="Ingreso"]'));
            self::assertCount(0, $crawler->filterXPath('//*[@name="animal[origen]"]'));

            $client->submit($crawler->selectButton('Registrar ingreso')->form([
                'animal[arete]' => $arete,
                'animal[finca]' => (string) $fixture['finca']->getId(),
                'animal[sexo]' => Animal::SEXO_MACHO,
                'animal[procedencia]' => $procedencia ?? '',
            ]));

            self::assertResponseRedirects('/animals');
            $animal = $this->entityManager($client)->getRepository(Animal::class)->findOneBy(['arete' => $arete]);
            self::assertInstanceOf(Animal::class, $animal);
            self::assertSame(Animal::ORIGEN_EXTERNO, $animal->getOrigen());
            self::assertSame($fixture['finca']->getId(), $animal->getFinca()->getId());
            self::assertSame($procedencia, $animal->getProcedencia());
        }
    }

    public function testBirthRequiresDateAndRejectsSubmittedOrigin(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $crawler = $client->request('GET', '/animals/new/birth');
        $token = $crawler->filterXPath('//input[@name="animal[_token]"]')->attr('value');

        $client->request('POST', '/animals/new/birth', ['animal' => [
            'arete' => 'BIRTH-NODATE-'.$fixture['suffix'],
            'finca' => (string) $fixture['finca']->getId(),
            'sexo' => Animal::SEXO_HEMBRA,
            '_token' => $token,
        ]]);

        self::assertStringContainsString('Indica la fecha de nacimiento.', (string) $client->getResponse()->getContent());
        self::assertNull($this->entityManager($client)->getRepository(Animal::class)->findOneBy(['arete' => 'BIRTH-NODATE-'.$fixture['suffix']]));

        $client->request('POST', '/animals/new/birth', ['animal' => [
            'arete' => 'BIRTH-TAMPER-'.$fixture['suffix'],
            'finca' => (string) $fixture['finca']->getId(),
            'sexo' => Animal::SEXO_HEMBRA,
            'fechaNacimiento' => '2026-01-15',
            'origen' => Animal::ORIGEN_EXTERNO,
            '_token' => $token,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager($client)->getRepository(Animal::class)->findOneBy(['arete' => 'BIRTH-TAMPER-'.$fixture['suffix']]));
    }

    public function testIntakeRequiresAuthentication(): void
    {
        $client = static::createClient();

        foreach (['/animals/new', '/animals/new/birth'] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('/login');
        }
    }

    public function testDuplicateEarTagIsRejectedInsideTheOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/animals/new');
        $client->submit($crawler->selectButton('Registrar ingreso')->form([
            'animal[arete]' => $fixture['animal']->getArete(),
            'animal[finca]' => (string) $fixture['finca']->getId(),
            'animal[sexo]' => Animal::SEXO_MACHO,
        ]));

        self::assertStringContainsString('El arete ya existe en la organización.', (string) $client->getResponse()->getContent());
    }

    public function testAdministratorCanEditAnAnimalWithoutChangingItsInternalCode(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $animalId = $fixture['animal']->getId();
        $codigoInterno = $fixture['animal']->getCodigoInterno();

        $crawler = $client->request('GET', sprintf('/animals/%d/edit', $animalId));
        self::assertStringNotContainsString($codigoInterno, (string) $client->getResponse()->getContent());
        $client->submit($crawler->selectButton('Guardar cambios')->form([
            'animal[arete]' => 'EDIT-'.$fixture['suffix'],
            'animal[nombre]' => 'Luna editada',
            'animal[finca]' => (string) $fixture['finca']->getId(),
            'animal[sexo]' => Animal::SEXO_HEMBRA,
            'animal[fechaNacimiento]' => '2025-01-15',
            'animal[pesoNacimientoKg]' => '42.50',
            'animal[origen]' => Animal::ORIGEN_EXTERNO,
            'animal[procedencia]' => 'Finca de origen',
            'animal[productoraLeche]' => '1',
        ]));

        self::assertResponseRedirects('/animals');
        $this->entityManager($client)->clear();
        $animal = $this->entityManager($client)->find(Animal::class, $animalId);
        self::assertSame($codigoInterno, $animal?->getCodigoInterno());
        self::assertSame('Luna editada', $animal?->getNombre());
        self::assertSame('42.50', $animal?->getPesoNacimientoKg());
        self::assertTrue($animal?->isProductoraLeche());
        self::assertSame($fixture['administrador']->getId(), $animal?->getActualizadoPor()?->getId());
    }

    public function testFarmManagerCanEditAssignedAnimalButNotAnotherFarmAnimal(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $crawler = $client->request('GET', sprintf('/animals/%d/edit', $fixture['animal']->getId()));
        $client->submit($crawler->selectButton('Guardar cambios')->form([
            'animal[arete]' => $fixture['animal']->getArete(),
            'animal[nombre]' => 'Editado por encargado',
            'animal[finca]' => (string) $fixture['finca']->getId(),
            'animal[sexo]' => Animal::SEXO_HEMBRA,
            'animal[origen]' => Animal::ORIGEN_EXTERNO,
        ]));

        self::assertResponseRedirects('/animals');
        $client->request('GET', sprintf('/animals/%d/edit', $fixture['animalOtraFinca']->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testFarmManagerOnlySeesAndOpensAssignedFarmAnimals(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $client->request('GET', '/animals');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString((string) $fixture['animal']->getArete(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString((string) $fixture['animalOtraFinca']->getArete(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString((string) $fixture['animalAjeno']->getArete(), (string) $client->getResponse()->getContent());

        $client->request('GET', sprintf('/animals/%d', $fixture['animal']->getId()));
        self::assertResponseIsSuccessful();

        $client->request('GET', sprintf('/animals/%d', $fixture['animalOtraFinca']->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testFarmManagerRegistrationOnlyOffersAssignedFarm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        foreach (['/animals/new', '/animals/new/birth'] as $path) {
            $crawler = $client->request('GET', $path);

            self::assertCount(1, $crawler->filterXPath(sprintf('//select[@name="animal[finca]"]/option[@value="%d"]', $fixture['finca']->getId())));
            self::assertCount(0, $crawler->filterXPath(sprintf('//select[@name="animal[finca]"]/option[@value="%d"]', $fixture['fincaAlterna']->getId())));

            $client->request('POST', $path, ['animal' => [
                'arete' => 'UNAUTHORIZED-'.$fixture['suffix'],
                'finca' => (string) $fixture['fincaAlterna']->getId(),
                'sexo' => Animal::SEXO_MACHO,
                'fechaNacimiento' => '2026-01-15',
                '_token' => $crawler->filterXPath('//input[@name="animal[_token]"]')->attr('value'),
            ]]);

            self::assertResponseStatusCodeSame(422);
            self::assertNull($this->entityManager($client)->getRepository(Animal::class)->findOneBy(['arete' => 'UNAUTHORIZED-'.$fixture['suffix']]));
        }
    }

    public function testAdministratorCannotAccessAnimalFromAnotherOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', sprintf('/animals/%d', $fixture['animalAjeno']->getId()));
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', sprintf('/animals/%d/edit', $fixture['animalAjeno']->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    /** @return array{suffix: string, organizacion: OrganizacionGanadera, finca: Finca, fincaAlterna: Finca, administrador: Usuario, encargado: Usuario, animal: Animal, animalOtraFinca: Animal, animalAjeno: Animal} */
    private function createFixture(KernelBrowser $client): array
    {
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $otraOrganizacion = new OrganizacionGanadera('Otra organización '.$suffix, 'OTRA-'.$suffix);
        $finca = (new Finca('Finca propia '.$suffix, 'FIN-'.$suffix))->setOrganizacion($organizacion);
        $fincaAlterna = (new Finca('Finca alterna '.$suffix, 'ALT-'.$suffix))->setOrganizacion($organizacion);
        $fincaAjena = (new Finca('Finca ajena '.$suffix, 'AJENA-'.$suffix))->setOrganizacion($otraOrganizacion);
        $administrador = (new Usuario('Administrador', 'admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado', 'manager-'.$suffix.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($finca);
        $animal = (new Animal($organizacion, $finca, 'AN-'.$suffix, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO))
            ->setArete('GT-'.$suffix);
        $animalOtraFinca = (new Animal($organizacion, $fincaAlterna, 'ALT-'.$suffix, Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO))
            ->setArete('OTRA-'.$suffix);
        $animalAjeno = (new Animal($otraOrganizacion, $fincaAjena, 'AJ-'.$suffix, Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO))
            ->setArete('AJENO-'.$suffix);

        foreach ([$organizacion, $otraOrganizacion, $finca, $fincaAlterna, $fincaAjena, $administrador, $encargado, $animal, $animalOtraFinca, $animalAjeno] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('suffix', 'organizacion', 'finca', 'fincaAlterna', 'administrador', 'encargado', 'animal', 'animalOtraFinca', 'animalAjeno');
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
