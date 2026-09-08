# Domain Rules

## Organización y acceso
- Toda información pertenece lógicamente a una organización ganadera.
- Un usuario no puede acceder a datos de otra organización.
- Un Encargado solo puede operar sobre su finca activa asignada.
- El Administrador puede consultar todas las fincas de su organización.

## Fincas
- El código de finca debe ser único dentro de la organización.
- Un Encargado tendrá una única finca activa a la vez.

## Animales
- Cada animal posee un código interno único a nivel global.
- El arete debe ser único dentro de la organización.
- Un animal pertenece actualmente a una finca.
- El origen será `NACIDO_EN_FINCA` o `EXTERNO`.
- La procedencia externa es descriptiva; no crea una finca externa.
- Solo una hembra puede marcarse como productora de leche.
- Un animal con historial no debe eliminarse físicamente.

## Genealogía
- Padre y madre son referencias opcionales a otros animales.
- La genealogía se deriva de estas relaciones.
- No se crea una tabla independiente de genealogía.

## Pesajes
- Unidad oficial: kilogramo.
- Peso > 0.
- Máximo un pesaje por animal y fecha.
- No puede registrarse un pesaje anterior al nacimiento.

## Vacunaciones
- Deben utilizar una vacuna disponible en catálogo.
- Se asocian al animal y forman parte de su historial.

## Reproducción
- La hembra es obligatoria.
- El macho es opcional.
- Los sexos deben ser coherentes con el rol reproductivo.
- Los animales relacionados deben pertenecer a la misma organización.

## Producción de leche
- Solo para hembras habilitadas como productoras.
- Puede haber múltiples registros por animal en una misma fecha.
- El total diario es calculado como la suma de los registros.

## Trazabilidad
Los registros operativos relevantes deben conservar:
- `creado_por`
- `fecha_creacion`
- `actualizado_por`
- `fecha_actualizacion`
