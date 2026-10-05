<?php

declare(strict_types=1);

namespace App\Entity\Concerns;

use App\Entity\Usuario;
use DateTimeImmutable;

/**
 * Registros con autoría (RNF-T-01): animal y los cuatro registros operativos.
 * El servidor asigna los valores desde el usuario autenticado; ningún formulario los recibe.
 */
interface AuditableInterface
{
    public function getCreadoPor(): ?Usuario;

    public function registrarCreacion(Usuario $usuario, DateTimeImmutable $fecha): void;

    public function registrarModificacion(Usuario $usuario, DateTimeImmutable $fecha): void;
}
