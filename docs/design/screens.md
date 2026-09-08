# Screen Specifications

## `/login`
Autenticación de Administrador o Encargado.

## `/dashboard`
Resumen simple del contexto autorizado. Puede incluir tarjetas o ilustraciones introductorias si aportan contexto, sin convertirse en analítica avanzada.

## `/animals`
Listado y búsqueda de animales autorizados.

## `/animals/new`
Registro general o nacimiento.

## `/animals/{id}`
Ficha integral con:
- Información general.
- Pesajes.
- Vacunaciones.
- Reproducción.
- Leche.
- Genealogía.

## `/animals/{id}/edit`
Modificación autorizada.

## Fincas
- `/farms`
- `/farms/new`
- `/farms/{id}/edit`

## Usuarios
- `/users`
- `/users/new`
- `/users/{id}/edit`

## Catálogos
- `/breeds`
- `/vaccines`

## Convención
Cada pantalla debe especificar:
- propósito;
- actor;
- acción primaria;
- estados vacío/error/carga;
- RF relacionado.
