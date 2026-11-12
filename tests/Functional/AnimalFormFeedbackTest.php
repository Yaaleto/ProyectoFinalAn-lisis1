<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Mensajes junto a su campo y regreso a la pantalla desde la que se abrió la edición. */
final class AnimalFormFeedbackTest extends WebTestCase
{
    public function testAMaleProducerIsRejectedUnderTheProducerCheckbox(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/animals/new');
        $formulario = $crawler->selectButton('Registrar ingreso')->form([
            'animal[arete]' => 'MACHO-'.$fixture['sufijo'],
            'animal[finca]' => (string) $fixture['finca']->getId(),
            'animal[sexo]' => Animal::SEXO_MACHO,
        ]);
        $formulario['animal[productoraLeche]']->tick();
        $client->submit($formulario);

        self::assertResponseStatusCodeSame(422);
        $crawler = $client->getCrawler();
        self::assertStringContainsString('Solo una hembra puede ser productora de leche.', $crawler->filterXPath('//*[starts-with(@id, "animal_productoraLeche_error")]')->text(''));
        self::assertCount(0, $crawler->filterXPath('//form/p[@role="alert"]'));
    }

    public function testEditingFromTheProfileReturnsToTheProfile(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $id = $fixture['animal']->getId();

        $crawler = $client->request('GET', sprintf('/animals/%d', $id));
        $crawler = $client->click($crawler->selectLink('Editar')->link());
        self::assertSame(sprintf('/animals/%d', $id), $crawler->selectLink('Cancelar')->attr('href'));
        $client->submit($crawler->selectButton('Guardar cambios')->form(['animal[nombre]' => 'Desde la ficha']));
        self::assertResponseRedirects(sprintf('/animals/%d', $id));

        $crawler = $client->request('GET', sprintf('/animals/%d/edit', $id));
        $client->submit($crawler->selectButton('Guardar cambios')->form(['animal[nombre]' => 'Desde el listado']));
        self::assertResponseRedirects('/animals');
    }

    /** @return array{sufijo: string, finca: Finca, administrador: Usuario, animal: Animal} */
    private function createFixture(KernelBrowser $client): array
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Ganadería '.$sufijo, 'FB-'.$sufijo);
        $finca = (new Finca('Finca '.$sufijo, 'FB-'.$sufijo))->setOrganizacion($organizacion);
        $administrador = (new Usuario('Administrador', 'fb-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))->setOrganizacion($organizacion);
        $animal = (new Animal($organizacion, $finca, 'AN-FB-'.$sufijo, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2026-03-01')))
            ->setArete('FB-'.$sufijo)
            ->setCreadoPor($administrador);
        foreach ([$organizacion, $finca, $administrador, $animal] as $entidad) {
            $em->persist($entidad);
        }
        $em->flush();

        return ['sufijo' => $sufijo, 'finca' => $finca, 'administrador' => $administrador, 'animal' => $animal];
    }
}
