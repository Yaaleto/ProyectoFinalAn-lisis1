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
        self::assertStringContainsString($fixture['animal']->getCodigoInterno(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($fixture['animalAjeno']->getCodigoInterno(), (string) $client->getResponse()->getContent());
    }

    /** @return array{administrador: Usuario, animal: Animal, animalAjeno: Animal} */
    private function createFixture(KernelBrowser $client): array
    {
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$suffix, 'ORG-'.$suffix);
        $otraOrganizacion = new OrganizacionGanadera('Otra organización '.$suffix, 'OTRA-'.$suffix);
        $finca = (new Finca('Finca propia '.$suffix, 'FIN-'.$suffix))->setOrganizacion($organizacion);
        $fincaAjena = (new Finca('Finca ajena '.$suffix, 'AJENA-'.$suffix))->setOrganizacion($otraOrganizacion);
        $administrador = (new Usuario('Administrador', 'admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $animal = new Animal($organizacion, $finca, 'AN-'.$suffix, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO);
        $animalAjeno = new Animal($otraOrganizacion, $fincaAjena, 'AJ-'.$suffix, Animal::SEXO_MACHO, Animal::ORIGEN_EXTERNO);

        foreach ([$organizacion, $otraOrganizacion, $finca, $fincaAjena, $administrador, $animal, $animalAjeno] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('administrador', 'animal', 'animalAjeno');
    }
}
