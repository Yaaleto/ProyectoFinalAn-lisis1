<?php

declare(strict_types=1);

namespace App\Validation;

use DomainException;

/**
 * Una fecha fuera de su rango permitido (RN-11, RN-12, RN-15, RN-19). Los controladores la muestran
 * bajo el campo de fecha, junto a los demás errores de ese campo.
 */
final class FechaFueraDeRangoException extends DomainException
{
    /** @param string|null $campo campo del formulario de animal que causa el error, si no es la fecha de nacimiento */
    public function __construct(string $message, public readonly ?string $campo = null)
    {
        parent::__construct($message);
    }
}
