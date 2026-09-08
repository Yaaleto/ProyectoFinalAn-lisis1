# ADR-004: Microsoft Fluent 2 Design System

## Estado
Aceptado.

## Contexto
BOVITRACK necesita una interfaz consistente y profesional sin diseñar un sistema visual completo desde cero.

## Decisión
Adoptar **Microsoft Fluent 2** como sistema de diseño base de BOVITRACK.

Fluent 2 será referencia para:
- jerarquía visual;
- tipografía;
- color y tokens semánticos;
- espaciado;
- radios;
- elevación;
- estados de interacción;
- accesibilidad;
- formularios;
- navegación;
- diálogos;
- feedback;
- tablas y superficies.

La identidad de BOVITRACK podrá personalizar:
- color de marca;
- logotipo;
- componentes específicos del dominio ganadero.

## Implementación
```text
Fluent 2
   ↓
BOVITRACK tokens
   ↓
Tailwind CSS
   ↓
Twig Components / Symfony UX Toolkit
```

## Paleta aprobada
- Azul primario: `#2514EB`
- Rojo de acción/alerta: `#F02C10`
- Neutro claro de superficie: `#E1E1DD`
- Oscuro profundo / fondo inverso: `#081A19`
- Púrpura complementario: `#C135EB`

## Iconografía
Usar Fluent System Icons como conjunto preferente.

## Imágenes e ilustraciones
Permitir ilustraciones o imágenes contextuales cuando aporten comprensión y no reemplacen claridad funcional.

## Consecuencias
- Menos decisiones visuales arbitrarias.
- Mayor consistencia entre pantallas.
- Componentes propios sin necesidad de React.
- Tailwind queda como mecanismo de implementación, no como lenguaje visual.
