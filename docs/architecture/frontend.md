# Frontend Architecture

## Principio
BOVITRACK usará una interfaz server-rendered con progressive enhancement.

## Flujo visual
```text
Microsoft Fluent 2
        ↓
BOVITRACK Design Tokens
        ↓
Tailwind CSS
        ↓
Symfony UX Toolkit / Twig Components
        ↓
BOVITRACK UI
```

## Regla de decisión
1. **Twig:** contenido renderizado por servidor.
2. **Twig Component:** UI reutilizable.
3. **Live Component:** interacción con PHP/BD sin recarga completa.
4. **Turbo:** navegación y actualización parcial.
5. **Stimulus:** comportamiento puramente del navegador.

## Uso de imágenes e ilustraciones
- Emplear ilustraciones o imágenes cuando mejoren comprensión y contexto.
- Priorizarlas en estados vacíos, paneles introductorios, tarjetas explicativas o feedback destacado.
- No reemplazar texto esencial por una imagen.
- Para acciones pequeñas y repetitivas, preferir iconos o controles estándar.

## Objetivo
Mantener la lógica de negocio en PHP y evitar JavaScript personalizado innecesario.

## Prohibiciones por defecto
- `fetch()` manual repetitivo.
- archivos JavaScript monolíticos.
- duplicar reglas de negocio en frontend.
- introducir otro framework frontend sin ADR.
