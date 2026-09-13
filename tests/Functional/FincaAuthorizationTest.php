<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use App\Repository\FincaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FincaAuthorizationTest extends WebTestCase
{
    public function testAdministratorCanAccessEveryFarmInItsOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', sprintf('/farms/%d', $fixture['fincaAlterna']->getId()));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Finca Alterna', (string) $client->getResponse()->getContent());
    }

    public function testFarmManagerIsBlockedFromAnotherFarmInItsOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['encargado']);

        $client->request('GET', sprintf('/farms/%d', $fixture['fincaAlterna']->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testUserIsBlockedFromAFarmInAnotherOrganization(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $client->loginUser($fixture['administrador']);

        $client->request('GET', sprintf('/farms/%d', $fixture['fincaAjena']->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnauthorizedUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);

        $client->request('GET', sprintf('/farms/%d', $fixture['fincaAsignada']->getId()));

        self::assertResponseRedirects('/login');
    }

    public function testAuthorizedQueriesNeverReturnForeignOrUnassignedFarms(): void
    {
        $client = static::createClient();
        $fixture = $this->createFixture($client);
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $repository = $entityManager->getRepository(Finca::class);

        self::assertInstanceOf(FincaRepository::class, $repository);

        $fincasAdministrador = $repository->createAuthorizedQueryBuilder($fixture['administrador'])
            ->getQuery()
            ->getResult();
        $fincasEncargado = $repository->createAuthorizedQueryBuilder($fixture['encargado'])
            ->getQuery()
            ->getResult();

        self::assertCount(2, $fincasAdministrador);
        self::assertSame([$fixture['fincaAsignada']->getId()], array_map(static fn (Finca $finca): ?int => $finca->getId(), $fincasEncargado));
    }

    /** @return array{administrador: Usuario, encargado: Usuario, fincaAsignada: Finca, fincaAlterna: Finca, fincaAjena: Finca} */
    private function createFixture(KernelBrowser $client): array
    {
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $organizacion = new OrganizacionGanadera('Ganadería Norte', 'NORTE-'.$suffix);
        $otraOrganizacion = new OrganizacionGanadera('Ganadería Sur', 'SUR-'.$suffix);
        $fincaAsignada = (new Finca('Finca Asignada', 'ASIG-'.$suffix))->setOrganizacion($organizacion);
        $fincaAlterna = (new Finca('Finca Alterna', 'ALTN-'.$suffix))->setOrganizacion($organizacion);
        $fincaAjena = (new Finca('Finca Ajena', 'AJEN-'.$suffix))->setOrganizacion($otraOrganizacion);
        $administrador = (new Usuario('Admin', 'admin-'.$suffix.'@example.com', 'hash', Usuario::ROL_ADMINISTRADOR))
            ->setOrganizacion($organizacion);
        $encargado = (new Usuario('Encargado', 'manager-'.$suffix.'@example.com', 'hash', Usuario::ROL_ENCARGADO))
            ->setOrganizacion($organizacion)
            ->setFincaActiva($fincaAsignada);

        foreach ([$organizacion, $otraOrganizacion, $fincaAsignada, $fincaAlterna, $fincaAjena, $administrador, $encargado] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return compact('administrador', 'encargado', 'fincaAsignada', 'fincaAlterna', 'fincaAjena');
    }
}
