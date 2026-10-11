<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Animal;
use App\Entity\Usuario;
use App\Security\FincaAccess;

/**
 * Genealogía ascendente de hasta tres generaciones (RF-10, RN-13): padres, abuelos y bisabuelos.
 * Cada posición indica si hay un ancestro registrado y si el usuario puede abrir su ficha.
 */
final class Genealogia
{
    public const GENERACIONES = 3;

    public function __construct(private readonly FincaAccess $fincaAccess)
    {
    }

    /**
     * Devuelve una lista por generación (1 = padres, 2 = abuelos, 3 = bisabuelos). Cada generación tiene
     * 2^n posiciones en orden padre/madre; una posición vacía significa ancestro no registrado.
     *
     * @return list<list<array{rol: string, animal: ?Animal, accesible: bool}>>
     */
    public function ascendencia(Animal $animal, Usuario $usuario): array
    {
        $generaciones = [];
        $anteriores = [$animal];

        for ($nivel = 1; $nivel <= self::GENERACIONES; ++$nivel) {
            $actual = [];
            foreach ($anteriores as $descendiente) {
                foreach (['padre' => $descendiente?->getPadre(), 'madre' => $descendiente?->getMadre()] as $rol => $ancestro) {
                    $actual[] = [
                        'rol' => self::rol($nivel, $rol),
                        'animal' => $ancestro,
                        'accesible' => null !== $ancestro && $this->fincaAccess->canView($usuario, $ancestro->getFinca()),
                    ];
                }
            }
            $generaciones[] = $actual;
            $anteriores = array_map(static fn (array $posicion): ?Animal => $posicion['animal'], $actual);
        }

        return $generaciones;
    }

    private static function rol(int $nivel, string $rol): string
    {
        $prefijo = match ($nivel) {
            1 => '',
            2 => 'Abuel',
            default => 'Bisabuel',
        };

        if (1 === $nivel) {
            return 'padre' === $rol ? 'Padre' : 'Madre';
        }

        return $prefijo.('padre' === $rol ? 'o' : 'a');
    }
}
