<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Finca;
use App\Entity\OrganizacionGanadera;
use App\Entity\Usuario;
use App\Security\UsuarioPasswordManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-demo', description: 'Create or refresh the local demo organization and users.')]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UsuarioPasswordManager $passwordManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $organizationRepository = $this->entityManager->getRepository(OrganizacionGanadera::class);
        $farmRepository = $this->entityManager->getRepository(Finca::class);
        $userRepository = $this->entityManager->getRepository(Usuario::class);

        $organization = $organizationRepository->findOneBy(['codigo' => 'BOVITRACK-DEMO'])
            ?? new OrganizacionGanadera('BOVITRACK Demo', 'BOVITRACK-DEMO');
        $this->entityManager->persist($organization);

        $farm = $farmRepository->findOneBy(['organizacion' => $organization, 'codigo' => 'DEMO-01'])
            ?? (new Finca('Finca Demo', 'DEMO-01'))->setOrganizacion($organization);
        $this->entityManager->persist($farm);

        $admin = $this->createOrRefreshUser(
            $userRepository->findOneBy(['email' => 'admin.demo@bovitrack.local']),
            'Administrador Demo',
            'admin.demo@bovitrack.local',
            Usuario::ROL_ADMINISTRADOR,
            $organization,
        );
        $manager = $this->createOrRefreshUser(
            $userRepository->findOneBy(['email' => 'encargado.demo@bovitrack.local']),
            'Encargado Demo',
            'encargado.demo@bovitrack.local',
            Usuario::ROL_ENCARGADO,
            $organization,
        )->setFincaActiva($farm);

        $this->entityManager->persist($admin);
        $this->entityManager->persist($manager);
        $this->entityManager->flush();

        $io->success('Datos demo disponibles. Contraseña para ambas cuentas: BoviTrackDemo2026!');

        return Command::SUCCESS;
    }

    private function createOrRefreshUser(
        ?Usuario $user,
        string $name,
        string $email,
        string $role,
        OrganizacionGanadera $organization,
    ): Usuario {
        $user ??= new Usuario($name, $email, '', $role);
        $user
            ->setNombre($name)
            ->setEmail($email)
            ->setRol($role)
            ->setActivo(true)
            ->setOrganizacion($organization);
        $this->passwordManager->setPassword($user, 'BoviTrackDemo2026!');

        return $user;
    }
}
