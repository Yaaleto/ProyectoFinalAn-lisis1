<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use App\Entity\Vacuna;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Límites del diccionario de datos (RNF-P-06): un valor fuera de rango se rechaza con un mensaje
 * en lugar de provocar un error de la base de datos.
 */
final class FieldLimitsTest extends WebTestCase
{
    /** @return iterable<string, array{string, string, array<string, string>, int}> */
    public static function casos(): iterable
    {
        yield 'observaciones de vacunación en el límite' => ['/vaccinations/new', 'vacunacion', ['vacunacion[observaciones]' => str_repeat('a', 200)], 302];
        yield 'observaciones de vacunación excedidas' => ['/vaccinations/new', 'vacunacion', ['vacunacion[observaciones]' => str_repeat('a', 201)], 422];
        yield 'observación de monta en el límite' => ['/matings/new', 'monta_reproductiva', ['monta_reproductiva[observacion]' => str_repeat('a', 200)], 302];
        yield 'observación de monta excedida' => ['/matings/new', 'monta_reproductiva', ['monta_reproductiva[observacion]' => str_repeat('a', 201)], 422];
        yield 'peso en el límite' => ['/weights/new', 'pesaje', ['pesaje[pesoKg]' => '9999.99'], 302];
        yield 'peso excedido' => ['/weights/new', 'pesaje', ['pesaje[pesoKg]' => '10000'], 422];
        yield 'leche en el límite' => ['/milk/new', 'produccion_leche', ['produccion_leche[cantidadLitros]' => '999.99'], 302];
        yield 'leche excedida' => ['/milk/new', 'produccion_leche', ['produccion_leche[cantidadLitros]' => '1000'], 422];
    }

    /**
     * @dataProvider casos
     *
     * @param array<string, string> $valores
     */
    public function testOperationalRecordLimits(string $ruta, string $formulario, array $valores, int $esperado): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $base = match ($formulario) {
            'vacunacion' => ['vacunacion[vacuna]' => (string) $fixture['vacuna']->getId(), 'vacunacion[fecha]' => '2026-03-01', 'vacunacion[dosis]' => '5 ml', 'vacunacion[observaciones]' => ''],
            'monta_reproductiva' => ['monta_reproductiva[fecha]' => '2026-03-01', 'monta_reproductiva[macho]' => '', 'monta_reproductiva[observacion]' => ''],
            'pesaje' => ['pesaje[fecha]' => '2026-03-01', 'pesaje[pesoKg]' => '400'],
            'produccion_leche' => ['produccion_leche[fincaOrdeno]' => (string) $fixture['finca']->getId(), 'produccion_leche[fechaHora]' => '2026-03-01T05:30', 'produccion_leche[cantidadLitros]' => '10'],
        };
        $clave = 'monta_reproductiva' === $formulario ? 'hembra' : 'animal';
        $crawler = $client->request('GET', $ruta);
        $client->request('POST', $ruta, [$formulario => array_merge(
            $this->aCampos($base),
            [$clave => (string) $fixture['vaca']->getId(), '_token' => $crawler->filterXPath(sprintf('//input[@name="%s[_token]"]', $formulario))->attr('value')],
            $this->aCampos($valores),
        )]);

        self::assertResponseStatusCodeSame($esperado);
    }

    public function testVaccineDescriptionLimit(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $crawler = $client->request('GET', '/vaccines/new');
        $client->submit($crawler->selectButton('Crear vacuna')->form([
            'vacuna[nombre]' => 'Vacuna larga '.$fixture['sufijo'],
            'vacuna[descripcion]' => str_repeat('d', 201),
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('La descripción admite hasta 200 caracteres.', (string) $client->getResponse()->getContent());

        $crawler = $client->request('GET', '/vaccines/new');
        $client->submit($crawler->selectButton('Crear vacuna')->form([
            'vacuna[nombre]' => 'Vacuna justa '.$fixture['sufijo'],
            'vacuna[descripcion]' => str_repeat('d', 200),
        ]));
        self::assertResponseRedirects('/vaccines');
    }

    /**
     * @param array<string, string> $campos
     *
     * @return array<string, string>
     */
    private function aCampos(array $campos): array
    {
        $resultado = [];
        foreach ($campos as $nombre => $valor) {
            $resultado[preg_replace('/^[a-z_]+\[([^\]]+)\]$/', '$1', $nombre)] = $valor;
        }

        return $resultado;
    }

    /** @return array<string, mixed> */
    private function createFixture(KernelBrowser $client): array
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Límites '.$sufijo, 'LIM-'.$sufijo);
        $finca = (new Finca('Finca '.$sufijo, 'LIM-'.$sufijo))->setOrganizacion($organizacion);
        $administrador = (new Usuario('Administrador', 'lim-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))->setOrganizacion($organizacion);
        $vacuna = new Vacuna($organizacion, 'Aftosa '.$sufijo);
        $vaca = (new Animal($organizacion, $finca, 'AN-LIM-'.$sufijo, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO, new DateTimeImmutable('2025-01-01')))
            ->setArete('LIM-'.$sufijo)
            ->definirFechas(new DateTimeImmutable('2025-01-01'), new DateTimeImmutable('2025-01-01'))
            ->setProductoraLeche(true)
            ->setCreadoPor($administrador);
        foreach ([$organizacion, $finca, $administrador, $vacuna, $vaca] as $entidad) {
            $em->persist($entidad);
        }
        $em->flush();

        return compact('sufijo', 'finca', 'administrador', 'vacuna', 'vaca');
    }
}
