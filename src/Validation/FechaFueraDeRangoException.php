<?php

declare(strict_types=1);

namespace App\Validation;

use DomainException;

/**
 * Una fecha fuera de su rango permitido (RN-11, RN-12, RN-15). Los controladores la muestran
 * bajo el campo de fecha, junto a los demás errores de ese campo.
 */
final class FechaFueraDeRangoException extends DomainException
{
}
