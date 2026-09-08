# ADR-005: Graphify para indexación y búsqueda del proyecto

## Estado

Aceptada

## Decisión

BOVITRACK usará [Graphify](https://github.com/Graphify-Labs/graphify) como índice navegable del código y la documentación del proyecto.

Graphify se instalará a nivel del proyecto para Codex y su salida se generará en `graphify-out/`. Las consultas de arquitectura, relaciones entre archivos y decisiones técnicas deben comenzar con `graphify query`, `graphify path` o `graphify explain` cuando exista un grafo disponible.

## Mantenimiento

- Después de cualquier cambio, ejecutar `python tools/manual_graph_index.py`.
- El índice de este proyecto es deliberadamente estructural: no analiza contenido con modelos ni requiere API keys.
- Revisar el grafo antes de responder preguntas sobre la estructura del proyecto; usar los archivos fuente directamente solo cuando el grafo no cubra la pregunta o esté siendo corregido.
- Mantener actualizados `graphify-out/graph.json` y `graphify-out/GRAPH_REPORT.md` junto con los cambios relevantes del proyecto.

## Consecuencias

Codex tendrá una guía persistente para consultar el grafo y una configuración local versionable. El índice versionado cubre jerarquías y enlaces explícitos; las relaciones semánticas quedan fuera hasta que se solicite expresamente.
