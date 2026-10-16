<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Disponibilidad;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Informe mensual de disponibilidad (RNF-P-08) a partir del registro del monitor de Compose. */
#[AsCommand(name: 'app:disponibilidad', description: 'Calcula la disponibilidad mensual entre las 05:00 y las 20:00 (RNF-P-08).')]
final class DisponibilidadCommand extends Command
{
    public function __construct(
        private readonly Disponibilidad $disponibilidad,
        #[Autowire('%kernel.project_dir%/var/disponibilidad/registro.log')]
        private readonly string $registroPorDefecto,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('mes', null, InputOption::VALUE_REQUIRED, 'Mes a evaluar (AAAA-MM).', (new DateTimeImmutable())->format('Y-m'))
            ->addOption('registro', null, InputOption::VALUE_REQUIRED, 'Ruta del registro del monitor.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $mes = (string) $input->getOption('mes');
        $registro = (string) ($input->getOption('registro') ?? $this->registroPorDefecto);

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $io->error('El mes debe tener el formato AAAA-MM.');

            return Command::INVALID;
        }
        if (!is_readable($registro)) {
            $io->error(sprintf('No existe el registro del monitor en %s. Levanta Compose para que el servicio "monitor" empiece a escribirlo.', $registro));

            return Command::FAILURE;
        }

        $resultado = $this->disponibilidad->calcular(file($registro, FILE_IGNORE_NEW_LINES) ?: [], $mes, new DateTimeImmutable());

        $io->title(sprintf('Disponibilidad de %s · jornada %02d:00 a %02d:00', $mes, Disponibilidad::HORA_INICIO, Disponibilidad::HORA_FIN));
        if (null === $resultado['porcentaje']) {
            $io->warning('Todavía no hay minutos de jornada que evaluar en ese mes.');

            return Command::SUCCESS;
        }

        $io->definitionList(
            ['Medición desde' => (string) $resultado['desde']],
            ['Minutos de jornada evaluados' => (string) $resultado['minutos']],
            ['Minutos disponibles' => (string) $resultado['disponibles']],
            ['Disponibilidad' => sprintf('%.2f %%', $resultado['porcentaje'])],
            ['Objetivo' => sprintf('%.0f %%', Disponibilidad::OBJETIVO)],
        );
        if ([] !== $resultado['caidas']) {
            $io->table(['Desde', 'Hasta', 'Minutos'], array_map(static fn (array $c): array => [$c['desde'], $c['hasta'], $c['minutos']], $resultado['caidas']));
        }
        $resultado['cumple']
            ? $io->success('Cumple RNF-P-08 en el periodo evaluado.')
            : $io->warning('No alcanza el objetivo de RNF-P-08 en el periodo evaluado.');

        return Command::SUCCESS;
    }
}
