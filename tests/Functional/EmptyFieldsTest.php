<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Animal;
use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Los formularios no usan la validación del navegador (novalidate), así que el servidor debe rechazar
 * con un mensaje cualquier campo vacío, sin errores internos.
 */
final class EmptyFieldsTest extends WebTestCase
{
    public function testEveryFormRejectsEachEmptyFieldWithoutAnInternalError(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $sufijo = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Ganadería '.$sufijo, 'VAC-'.$sufijo);
        $finca = (new Finca('Finca '.$sufijo, 'VAC-'.$sufijo))->setOrganizacion($organizacion);
        $administrador = (new Usuario('Administrador', 'vac-admin-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado', 'vac-enc-'.$sufijo.'@example.com', 'hash', Usuario::ROL_ENCARGADO))->setOrganizacion($organizacion)->setFincaActiva($finca);
        $animal = (new Animal($organizacion, $finca, 'AN-'.$sufijo, Animal::SEXO_HEMBRA, Animal::ORIGEN_EXTERNO))
            ->setArete('VAC-'.$sufijo)
            ->setCreadoPor($administrador);
        foreach ([$organizacion, $finca, $administrador, $encargado, $animal] as $entidad) {
            $em->persist($entidad);
        }
        $em->flush();
        $client->loginUser($administrador);

        $rutas = [
            '/animals/new', '/animals/new/birth', '/animals/'.$animal->getId().'/edit', '/animals/'.$animal->getId().'/status',
            '/farms/new', '/farms/'.$finca->getId().'/edit', '/users/new', '/users/'.$encargado->getId().'/edit',
            '/weights/new', '/vaccinations/new', '/matings/new', '/milk/new', '/breeds/new', '/vaccines/new',
        ];
        foreach ($rutas as $ruta) {
            $crawler = $client->request('GET', $ruta);
            self::assertResponseIsSuccessful($ruta);
            $formulario = $crawler->filterXPath('//main//form[@novalidate]')->form();
            $valores = $formulario->getValues();
            $campos = array_keys(array_filter($valores, static fn (string $campo): bool => !str_ends_with($campo, '[_token]'), ARRAY_FILTER_USE_KEY));

            foreach ([...$campos, '*'] as $vacio) {
                $enviados = $valores;
                foreach ($campos as $campo) {
                    if ('*' === $vacio || $campo === $vacio) {
                        $enviados[$campo] = '';
                    }
                }
                parse_str(http_build_query($enviados), $datos);
                $client->request('POST', $formulario->getUri(), $datos);

                self::assertLessThan(500, $client->getResponse()->getStatusCode(), sprintf('%s con %s vacío', $ruta, $vacio));
                // Cada regla lleva un mensaje propio en español, no el genérico del validador.
                // El asterisco (atributo required) coincide con lo que exige el servidor.
                // La finca asignada solo es obligatoria para los encargados, como indica su ayuda.
                if ('*' !== $vacio && 'usuario[fincaActiva]' !== $vacio) {
                    $id = $crawler->filterXPath(sprintf('//*[@name="%s"]', $vacio))->attr('id');
                    $obligatorio = str_ends_with(trim($crawler->filterXPath(sprintf('//label[@for="%s"]', $id))->text('')), ' *');
                    $enviado = $client->getCrawler()->filterXPath(sprintf('//*[@name="%s"]', $vacio));
                    // Si el formulario se guardó (redirección), el campo vacío fue aceptado.
                    $rechazado = $enviado->count() > 0 && 'true' === $enviado->attr('aria-invalid');
                    if ($obligatorio !== $rechazado) {
                        $incoherentes[] = sprintf('%s · %s: %s', $ruta, $vacio, $obligatorio ? 'lleva * pero se acepta vacío' : 'sin * pero se rechaza vacío');
                    }
                }
                foreach ($client->getCrawler()->filterXPath('//*[@role="alert"]') as $alerta) {
                    $mensaje = trim($alerta->textContent);
                    if (1 === preg_match('/This value|This collection|Este valor|Esta colección/u', $mensaje)) {
                        $genericos[sprintf('%s · %s', strtok($ruta, '?'), $alerta->attributes?->getNamedItem('id')?->nodeValue)] = $mensaje;
                    }
                }
            }
        }

        self::assertSame([], $genericos ?? []);
        self::assertSame([], $incoherentes ?? []);
    }
}
