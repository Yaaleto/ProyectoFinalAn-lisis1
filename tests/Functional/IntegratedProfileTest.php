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
use App\Entity\Vacuna;
use App\Entity\Vacunacion;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IntegratedProfileTest extends WebTestCase
{
    public function testAdministratorFindsAnimalsByEarTagOrNameInsideItsOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        foreach ([substr($fixture['vaca']->getArete(), 0, 8), 'Lucero'] as $busqueda) {
            $client->request('GET', '/animals?q='.urlencode($busqueda));
            $contenido = (string) $client->getResponse()->getContent();
            self::assertStringContainsString($fixture['vaca']->getArete(), $contenido, $busqueda);
            self::assertStringNotContainsString($fixture['ajeno']->getArete(), $contenido, $busqueda);
        }

        $client->request('GET', '/animals?q=Lucero');
        self::assertStringNotContainsString($fixture['toro']->getArete(), (string) $client->getResponse()->getContent());
    }

    public function testSearchNeverReturnsAnimalsOutsideTheUserScope(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $crawler = $client->request('GET', '/animals?q='.urlencode($fixture['sufijo']));
        // Se comparan aretes completos: "BISABUELO-…" contiene el texto "ABUELO-…".
        $aretes = $crawler->filterXPath('//tbody/tr/td[1]')->each(static fn ($celda) => trim($celda->text()));
        self::assertContains($fixture['vaca']->getArete(), $aretes);
        self::assertNotContains($fixture['abueloOtraFinca']->getArete(), $aretes);
        self::assertNotContains($fixture['ajeno']->getArete(), $aretes);

        // Los comodines de LIKE se buscan como texto literal, no devuelven todos los animales.
        foreach (['%', '_'] as $comodin) {
            $client->request('GET', '/animals', ['q' => $comodin]);
            self::assertStringContainsString('Ningún animal coincide', (string) $client->getResponse()->getContent(), $comodin);
        }
    }

    public function testTheProfileGathersTheFourHistoriesAndKeepsTheMilkingFarm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $em = $this->entityManager($client);
        $vacuna = new Vacuna($fixture['organizacion'], 'Aftosa '.$fixture['sufijo']);
        $em->persist($vacuna);
        $em->persist(new Pesaje($fixture['vaca'], new DateTimeImmutable('2026-03-01'), '400.00'));
        $em->persist(new Pesaje($fixture['vaca'], new DateTimeImmutable('2026-04-01'), '412.50'));
        $em->persist(new Vacunacion($fixture['vaca'], $vacuna, new DateTimeImmutable('2026-03-05'), '5 ml'));
        $em->persist(new MontaReproductiva($fixture['vaca'], $fixture['toro'], new DateTimeImmutable('2026-03-10')));
        $em->persist(new ProduccionLeche($fixture['vaca'], $fixture['finca'], new DateTimeImmutable('2026-03-15 05:30'), '12.00'));
        $em->persist(new ProduccionLeche($fixture['vaca'], $fixture['finca'], new DateTimeImmutable('2026-03-15 17:00'), '9.50'));
        $em->flush();
        $fixture['vaca']->setFinca($fixture['fincaAlterna']);
        $em->flush();

        $crawler = $client->request('GET', sprintf('/animals/%d', $fixture['vaca']->getId()));
        self::assertResponseIsSuccessful();
        $seccion = static fn (string $nombre): string => preg_replace('/\s+/', ' ', $crawler->filterXPath(sprintf('//section[@data-profile-section="%s"]', $nombre))->text());

        self::assertStringContainsString('412.50 kg +12.50 kg', $seccion('pesajes'));
        self::assertStringContainsString('Aftosa '.$fixture['sufijo'], $seccion('vacunaciones'));
        self::assertStringContainsString($fixture['toro']->getArete(), $seccion('montas'));
        self::assertStringContainsString('15/03/2026 2 21.50 L', $seccion('leche'));
        $ordenos = $crawler->filterXPath('//tr[@data-profile-milking]')->each(static fn ($fila) => preg_replace('/\s+/', ' ', trim($fila->text())));
        self::assertCount(2, $ordenos);
        self::assertStringContainsString($fixture['finca']->getNombre(), $ordenos[0]);
        self::assertCount(0, $crawler->filterXPath('//*[@data-empty-section]'));
        self::assertStringNotContainsString($fixture['vaca']->getCodigoInterno(), (string) $client->getResponse()->getContent());
    }

    public function testSectionsWithoutRecordsAreShownEmpty(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', sprintf('/animals/%d', $fixture['vaca']->getId()));

        $vacios = $crawler->filterXPath('//*[@data-empty-section]')->each(static fn ($nodo) => trim($nodo->text()));
        self::assertSame(['Sin pesajes registrados.', 'Sin vacunaciones registradas.', 'Sin montas registradas.', 'Sin ordeños registrados.'], $vacios);
        self::assertCount(0, $crawler->filterXPath('//tr[@data-profile-milking]'));
    }

    public function testGenealogyShowsThreeGenerationsAndHandlesMissingAncestors(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', sprintf('/animals/%d', $fixture['vaca']->getId()));

        $generacion = static fn (int $n): array => $crawler->filterXPath(sprintf('//div[@data-generation="%d"]//div[@data-ancestor]', $n))
            ->each(static fn ($nodo) => preg_replace('/\s+/', ' ', trim($nodo->text())));
        self::assertCount(3, $crawler->filterXPath('//div[@data-generation]'));
        self::assertCount(2, $generacion(1));
        self::assertCount(4, $generacion(2));
        self::assertCount(8, $generacion(3));
        self::assertStringContainsString('Padre '.$fixture['padre']->getNombre(), $generacion(1)[0]);
        self::assertSame('Madre Sin registrar', $generacion(1)[1]);
        self::assertStringContainsString('Abuelo '.$fixture['abueloOtraFinca']->getNombre(), $generacion(2)[0]);
        self::assertStringContainsString('Bisabuelo '.$fixture['bisabuelo']->getNombre(), $generacion(3)[0]);
        self::assertSame('Bisabuela Sin registrar', $generacion(3)[7]);
        self::assertStringNotContainsString($fixture['tatarabuelo']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertCount(1, $crawler->filterXPath(sprintf('//a[@href="/animals/%d"]', $fixture['abueloOtraFinca']->getId())));
    }

    public function testFarmManagerSeesAncestorsOfAnotherFarmWithoutOpeningTheirProfile(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $crawler = $client->request('GET', sprintf('/animals/%d', $fixture['vaca']->getId()));

        self::assertStringContainsString($fixture['abueloOtraFinca']->getNombre(), (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Otra finca · ficha no disponible', (string) $client->getResponse()->getContent());
        self::assertCount(0, $crawler->filterXPath(sprintf('//a[@href="/animals/%d"]', $fixture['abueloOtraFinca']->getId())));
        self::assertCount(1, $crawler->filterXPath(sprintf('//a[@href="/animals/%d"]', $fixture['padre']->getId())));

        $client->request('GET', sprintf('/animals/%d', $fixture['abueloOtraFinca']->getId()));
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', sprintf('/animals/%d', $fixture['ajeno']->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testFarmManagerDoesNotSeeMilkingsThatHappenedOnAnotherFarm(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);
        $em = $this->entityManager($client);
        $em->persist(new ProduccionLeche($fixture['vaca'], $fixture['finca'], new DateTimeImmutable('2026-03-15 05:30'), '12.00'));
        $em->persist(new ProduccionLeche($fixture['vaca'], $fixture['fincaAlterna'], new DateTimeImmutable('2026-03-16 05:30'), '77.00'));
        $em->flush();

        $client->loginUser($fixture['encargado']);
        foreach ([sprintf('/animals/%d', $fixture['vaca']->getId()), sprintf('/animals/%d/milk', $fixture['vaca']->getId())] as $ruta) {
            $client->request('GET', $ruta);
            $contenido = (string) $client->getResponse()->getContent();
            self::assertStringContainsString('12.00 L', $contenido, $ruta);
            self::assertStringNotContainsString('77.00 L', $contenido, $ruta);
        }

        $client->loginUser($fixture['administrador']);
        $client->request('GET', sprintf('/animals/%d', $fixture['vaca']->getId()));
        self::assertStringContainsString('77.00 L', (string) $client->getResponse()->getContent());
    }

    public function testViewingTheProfileDoesNotModifyTheAnimal(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', sprintf('/animals/%d', $fixture['vaca']->getId()));

        $fila = $this->entityManager($client)->getConnection()->fetchAssociative(
            'SELECT modificado_por, fecha_actualizacion FROM animal WHERE id_animal = ?',
            [$fixture['vaca']->getId()],
        );
        self::assertNull($fila['modificado_por']);
        self::assertNull($fila['fecha_actualizacion']);
    }

    /** @return array<string, mixed> */
    private function createFixture(KernelBrowser $client): array
    {
        $em = $this->entityManager($client);
        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Organización '.$sufijo, 'FICHA-'.$sufijo);
        $otraOrganizacion = new OrganizacionGanadera('Otra '.$sufijo, 'FICHA-OTRA-'.$sufijo);
        $finca = (new Finca('Finca propia '.$sufijo, 'FIC-'.$sufijo))->setOrganizacion($organizacion);
        $fincaAlterna = (new Finca('Finca alterna '.$sufijo, 'FIC-ALT-'.$sufijo))->setOrganizacion($organizacion);
        $fincaAjena = (new Finca('Finca ajena '.$sufijo, 'FIC-AJ-'.$sufijo))->setOrganizacion($otraOrganizacion);
        $administrador = (new Usuario('Administrador', 'ficha-admin-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))->setOrganizacion($organizacion);
        $administradorAjeno = (new Usuario('Ajeno', 'ficha-otro-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))->setOrganizacion($otraOrganizacion);
        $encargado = (new Usuario('Encargado', 'ficha-manager-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($finca);
        $nuevo = static fn (OrganizacionGanadera $org, Finca $f, string $clave, string $nombre, string $sexo, Usuario $creador): Animal => (new Animal($org, $f, 'AN-'.$clave.'-'.$sufijo, $sexo, Animal::ORIGEN_EXTERNO))
            ->setArete($clave.'-'.$sufijo)
            ->setNombre($nombre)
            ->setFechaNacimiento(new DateTimeImmutable('2020-01-10'))
            ->setCreadoPor($creador);

        $tatarabuelo = $nuevo($organizacion, $finca, 'TATARABUELO', 'Tatarabuelo '.$sufijo, Animal::SEXO_MACHO, $administrador);
        $bisabuelo = $nuevo($organizacion, $finca, 'BISABUELO', 'Bisabuelo '.$sufijo, Animal::SEXO_MACHO, $administrador)->setPadre($tatarabuelo);
        $abueloOtraFinca = $nuevo($organizacion, $fincaAlterna, 'ABUELO', 'Abuelo '.$sufijo, Animal::SEXO_MACHO, $administrador)->setPadre($bisabuelo);
        $padre = $nuevo($organizacion, $finca, 'PADRE', 'Padre '.$sufijo, Animal::SEXO_MACHO, $administrador)->setPadre($abueloOtraFinca);
        $vaca = $nuevo($organizacion, $finca, 'VACA', 'Lucero '.$sufijo, Animal::SEXO_HEMBRA, $administrador)
            ->setProductoraLeche(true)
            ->setPadre($padre);
        $toro = $nuevo($organizacion, $finca, 'TORO', 'Toro '.$sufijo, Animal::SEXO_MACHO, $administrador);
        $ajeno = $nuevo($otraOrganizacion, $fincaAjena, 'AJENO', 'Lucero ajeno '.$sufijo, Animal::SEXO_HEMBRA, $administradorAjeno);

        foreach ([$organizacion, $otraOrganizacion, $finca, $fincaAlterna, $fincaAjena, $administrador, $administradorAjeno, $encargado, $tatarabuelo, $bisabuelo, $abueloOtraFinca, $padre, $vaca, $toro, $ajeno] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return compact('sufijo', 'organizacion', 'finca', 'fincaAlterna', 'administrador', 'encargado', 'tatarabuelo', 'bisabuelo', 'abueloOtraFinca', 'padre', 'vaca', 'toro', 'ajeno');
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }
}
