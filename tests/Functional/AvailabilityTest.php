<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AvailabilityTest extends WebTestCase
{
    public function testStatusIsPublicAndReportsTheDatabase(): void
    {
        $client = static::createClient();

        $client->request('GET', '/status');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $cuerpo = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame('ok', $cuerpo['estado']);
        self::assertStringEndsWith('-06:00', $cuerpo['hora']);
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testTheCommandSummarisesTheMonitorLog(): void
    {
        $registro = tempnam(sys_get_temp_dir(), 'disponibilidad');
        $lineas = [];
        for ($m = 0; $m < 60; ++$m) {
            $lineas[] = sprintf('2026-09-30T06:%02d:10 %s', $m, $m < 57 ? 'ok' : 'caida');
        }
        file_put_contents($registro, implode("\n", $lineas)."\n");

        $kernel = static::bootKernel();
        $tester = new CommandTester((new Application($kernel))->find('app:disponibilidad'));
        $tester->execute(['--mes' => '2026-09', '--registro' => $registro]);
        unlink($registro);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $salida = preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('Medición desde 2026-09-30 06:00', $salida);
        self::assertStringContainsString('2026-09-30 06:57 2026-09-30 19:59', $salida);
        self::assertStringContainsString('No alcanza el objetivo de RNF-P-08', $salida);
    }

    public function testTheCommandExplainsAMissingLog(): void
    {
        $kernel = static::bootKernel();
        $tester = new CommandTester((new Application($kernel))->find('app:disponibilidad'));

        $tester->execute(['--registro' => '/no/existe.log']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No existe el registro del monitor', $tester->getDisplay());
    }
}
