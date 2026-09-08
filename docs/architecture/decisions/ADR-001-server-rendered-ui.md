# ADR-001: Server-rendered UI

## Estado
Aceptado.

## Contexto
BOVITRACK debe completarse en un periodo corto y no requiere mantener un frontend SPA separado.

## Decisión
Usar Symfony + Twig como base de renderizado.

## Consecuencias
- Menor complejidad.
- Una sola aplicación.
- Autorización concentrada en Symfony.
- Interactividad progresiva mediante Symfony UX.
