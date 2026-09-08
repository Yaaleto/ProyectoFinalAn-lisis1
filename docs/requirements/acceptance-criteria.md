# Acceptance Criteria

## RF-01
- Credenciales válidas permiten acceso autorizado.
- Credenciales inválidas rechazan el acceso.
- No es posible consultar datos de otra organización.

## RF-02
- El Administrador puede registrar o modificar usuarios y fincas válidos.
- Cambiar la asignación deja una única finca activa para el Encargado.
- No se permite código de finca duplicado dentro de la organización.

## RF-03
- Se pueden registrar razas válidas y no duplicadas.
- Una raza utilizada se desactiva en lugar de eliminarse físicamente.
- El Encargado solo selecciona elementos disponibles.

## RF-04
- Registro de animal genera código interno global único.
- Arete duplicado dentro de la organización se rechaza.
- Los estados de salida conservan historial.
- Animal con historial no se elimina físicamente.

## RF-05
- Nacimiento en finca asigna automáticamente finca y origen.
- Progenitores válidos quedan relacionados.
- Animal externo usa procedencia opcional.

## RF-06
- Peso válido se almacena.
- No se permite más de un pesaje por fecha y animal.
- No se permiten fechas anteriores al nacimiento.

## RF-07
- Vacunación válida se almacena y aparece en historial.
- Encargado no puede operar sobre animales de otra finca.

## RF-08
- Monta válida se almacena.
- Sexos incompatibles se rechazan.
- No se relacionan animales de organizaciones distintas.

## RF-09
- Producción > 0 se almacena.
- Solo se permite para hembras productoras.
- Se permiten múltiples registros por fecha.
- Total diario = suma de registros.

## RF-10
- Búsqueda muestra solo animales autorizados.
- Historial presenta registros existentes.
- Ausencia de registros no genera información ficticia.
