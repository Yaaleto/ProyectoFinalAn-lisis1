# DERCAS - Documento de Especificación de Requerimientos

## BOVITRACK - Sistema Web de Gestión Ganadera

| Campo | Valor |
|---|---|
| Versión del documento | 1.0 |
| Versión del sistema | V1/MVP |
| Estado | En validación |
| Fecha | Agosto de 2026 |
| Autor | Yahir Zabaleta |
| Cliente | Hendrick Calderón |
| Fuente | [`Documento de Especificación de Requerimientos - DERCAS.pdf`](../../documentacion/dercas/Documento%20de%20Especificaci%C3%B3n%20de%20Requerimientos%20-%20DERCAS.pdf) |

> Esta guía Markdown es la referencia operativa del DERCAS. El PDF original se conserva como respaldo documental.

## Control de versiones

| Versión | Fecha | Autor | Descripción | Estado |
|---|---|---|---|---|
| 0.1 | 24/08/2026 | Yahir Zabaleta | Documento preliminar | Borrador |
| 1.0 | 28/08/2026 | Yahir Zabaleta | Primera línea base formal de requerimientos | En validación |

## 1. Introducción

### 1.1 Propósito

Establecer de forma clara, estructurada, verificable y trazable los requerimientos funcionales y no funcionales de la primera versión de BOVITRACK. El documento orienta el análisis, diseño, implementación, pruebas y validación, y fija los límites funcionales, reglas de negocio, actores, restricciones técnicas y criterios de cumplimiento.

### 1.2 Objetivo del sistema

BOVITRACK será una aplicación web responsive para que organizaciones ganaderas gestionen de forma centralizada e independiente la información de sus fincas y animales, garantizando la segregación de datos entre organizaciones. Permitirá registrar, consultar y dar seguimiento a identificación animal, nacimientos, pesajes, vacunaciones, reproducción básica, genealogía y producción de leche.

### 1.3 Alcance V1/MVP

Incluye autenticación y control básico de usuarios, administración de fincas, registro y consulta de animales, nacimientos, peso, vacunación, reproducción básica, genealogía de hasta tres generaciones, producción diaria de leche, cálculo de producción diaria e historial integral por animal.

Las funcionalidades declaradas fuera del alcance no son criterios de aceptación de V1 y podrán evaluarse como ampliaciones posteriores.

### 1.4 Problema

La información ganadera suele estar distribuida entre registros manuales, hojas de cálculo y medios independientes. Esto dificulta contar con una fuente centralizada, consultar históricos y relacionar los datos de un mismo animal, además de aumentar el riesgo de duplicidad, inconsistencia y pérdida de información.

### 1.5 Actores

| Actor | Tipo | Alcance | Funciones principales |
|---|---|---|---|
| Administrador | Principal | Todas las fincas de su organización | Encargados, fincas, animales, registros y consultas |
| Encargado de finca | Principal | Únicamente su finca asignada | Animales, pesajes, vacunas, reproducción y leche |

## 2. Glosario

| Término | Definición |
|---|---|
| BOVITRACK | Sistema web para gestión y seguimiento básico de información ganadera. |
| Organización ganadera | Propietario lógico de la información y límite de segregación de datos. |
| Administrador | Usuario que administra usuarios, fincas, catálogos, animales y registros de su organización. |
| Encargado de finca | Usuario operativo asignado a una única finca activa. |
| Finca | Unidad productiva de una organización donde se registran animales. |
| Animal | Ejemplar bovino identificado individualmente. |
| Arete | Número usado para reconocer al animal dentro de una organización. |
| Código interno | Identificador único generado por BOVITRACK a nivel global. |
| Historial animal | Registros asociados a un animal: pesajes, vacunas, reproducción y leche. |
| Genealogía | Ascendencia obtenida por las referencias de padre y madre. |
| Monta reproductiva | Relación reproductiva básica entre una hembra y un macho conocido u opcional. |
| Producción de leche | Cantidad registrada para una hembra productora en una fecha. |
| V1/MVP | Primera versión funcional limitada a las funcionalidades esenciales. |

## 3. Requerimientos de usuario

| ID | Actor | Requerimiento | Prioridad |
|---|---|---|---|
| RU-01 | Administrador / Encargado | Acceder únicamente a la información de la organización y finca correspondiente a su rol. | Alta |
| RU-02 | Administrador | Gestionar usuarios, fincas, asignaciones y catálogos básicos de su organización. | Alta |
| RU-03 | Administrador / Encargado | Registrar, consultar y actualizar animales de las fincas autorizadas. | Alta |
| RU-04 | Administrador / Encargado | Registrar nacimientos e identificar animales incorporados desde fuera de la organización. | Alta |
| RU-05 | Administrador / Encargado | Registrar y consultar pesajes, vacunaciones, reproducción básica y producción de leche. | Alta |
| RU-06 | Administrador / Encargado | Consultar el historial integral y genealogía disponible de cada animal autorizado. | Alta |
| RU-07 | Administrador / Encargado | Corregir información permitida por rol conservando integridad y trazabilidad. | Alta |

## 4. Requerimientos funcionales

### RF-01 - Autenticación y control de acceso

El Administrador y el Encargado podrán iniciar y cerrar sesión. Las funciones y datos se autorizarán según rol, organización y, cuando corresponda, finca activa. El acceso a otra organización debe ser imposible.

**Criterios de aceptación:**

- Credenciales válidas permiten acceso solo a funciones autorizadas.
- Credenciales inválidas rechazan el acceso con un mensaje comprensible.
- Un usuario no puede consultar información de otra organización.

### RF-02 - Administración de usuarios y fincas

El Administrador podrá gestionar usuarios y fincas de su organización, y asignar o modificar la finca activa de cada Encargado.

**Criterios de aceptación:**

- Los cambios válidos quedan almacenados dentro de la organización.
- Un Encargado conserva una única finca activa autorizada después de cambiar su asignación.
- No se permiten códigos de finca duplicados dentro de una organización.

### RF-03 - Gestión de catálogos básicos

El Administrador podrá mantener catálogos de razas y vacunas. El Encargado solo podrá consultar y seleccionar elementos disponibles. Los elementos con historial no se eliminan físicamente: se desactivan.

**Criterios de aceptación:**

- Se registra una raza válida con nombre no utilizado.
- Una raza asociada a animales no se elimina físicamente.
- El Encargado solo ve razas disponibles para nuevos registros.

### RF-04 - Gestión de animales

Los usuarios autorizados podrán registrar, consultar y modificar animales. Cada animal tendrá un código interno global único y podrá incluir arete, nombre, sexo, fecha de nacimiento, peso al nacimiento, finca actual, origen, procedencia, progenitores, condición de productora de leche y estado.

**Criterios de aceptación:**

- El registro válido genera y almacena un código interno global único.
- No se permite un arete duplicado dentro de la organización.
- Los estados `VENDIDO`, `FALLECIDO` y `EGRESADO` conservan la información histórica e impiden nuevos registros operativos posteriores a la fecha indicada.
- Un animal con historial no se elimina físicamente.

### RF-05 - Registro de nacimientos y genealogía

Se podrá registrar un nacimiento ocurrido en una finca y relacionar opcionalmente madre y padre cuando sean conocidos. Un animal externo se registra por la gestión general con origen `EXTERNO` y procedencia opcional.

**Criterios de aceptación:**

- Un nacimiento válido crea la ficha, asigna la finca y establece origen `NACIDO_EN_FINCA`.
- Progenitores registrados y válidos quedan relacionados.
- Un animal externo usa origen `EXTERNO` y permite procedencia descriptiva.

### RF-06 - Seguimiento de pesajes

Se podrán registrar, consultar y modificar pesajes de animales habilitados, usando kilogramos como unidad oficial. Cada animal tendrá como máximo un pesaje por fecha.

**Criterios de aceptación:**

- Se almacena un peso mayor que cero con fecha válida.
- No se permite más de un pesaje por animal y fecha; debe modificarse el existente.
- No se permite una fecha anterior al nacimiento.

### RF-07 - Gestión de vacunaciones

Se podrán registrar, consultar y modificar aplicaciones de vacunas con vacuna, fecha, dosis y observaciones. La vacuna debe existir y estar disponible en el catálogo.

**Criterios de aceptación:**

- Una vacunación válida queda almacenada y aparece en el historial.
- El Encargado no puede vacunar animales de una finca distinta a la asignada.

### RF-08 - Registro de monta reproductiva

Se podrá registrar y modificar una monta indicando obligatoriamente hembra y fecha, y opcionalmente macho y observación. La monta se guarda en el historial reproductivo de la hembra.

**Criterios de aceptación:**

- Una monta válida queda almacenada.
- Un macho no puede seleccionarse como hembra.
- Una hembra no puede seleccionarse como macho.
- No se permite relacionar animales de organizaciones distintas.

### RF-09 - Gestión de producción de leche

Se podrán registrar, consultar y modificar cantidades de leche para hembras habilitadas como productoras. Se permiten múltiples registros por animal y fecha; la producción diaria es la suma de esos registros.

**Criterios de aceptación:**

- Se almacena una cantidad mayor que cero en una fecha válida.
- No se permite registrar producción para un animal que no sea hembra productora.
- Se permiten múltiples registros en la misma fecha.
- El total diario muestra la suma de todos los registros de esa fecha.

### RF-10 - Consulta integral del animal

Los usuarios autorizados podrán buscar por arete o código interno y consultar en una sola ficha la información básica, finca, estado, progenitores, genealogía disponible e historial de pesajes, vacunas, reproducción y leche.

**Criterios de aceptación:**

- La búsqueda solo muestra animales autorizados.
- La ficha presenta los registros históricos disponibles.
- La ausencia de un tipo de registro no genera datos inexistentes.

## 4.2 Requerimientos no funcionales

| ID | Categoría | Requerimiento | Verificación |
|---|---|---|---|
| RNF-SEG-01 | Seguridad | Toda función privada requiere autenticación y restricciones por rol, organización y finca. | Pruebas de autorización. |
| RNF-SEG-02 | Seguridad | Contraseñas con hashing seguro y mínimo 8 caracteres. | Inspección técnica y prueba negativa. |
| RNF-REN-01 | Rendimiento | Con hasta 15 usuarios concurrentes y 5,000 animales, al menos 95% de operaciones habituales responde en ≤ 5 segundos. | Prueba de rendimiento. |
| RNF-USA-01 | Usabilidad | Interfaz adaptable a computadoras y móviles. | Inspección y prueba responsive. |
| RNF-USA-02 | Usabilidad | Navegación y mensajes de validación comprensibles. | Prueba funcional y de interfaz. |
| RNF-INT-01 | Integridad | Validar formatos, valores, fechas y unicidad antes de almacenar. | Pruebas positivas, negativas y restricciones de BD. |
| RNF-TRA-01 | Trazabilidad | Conservar creación y última modificación de registros operativos relevantes. | Inspección de BD y prueba funcional. |

Campos mínimos de trazabilidad:

- `creado_por`
- `fecha_creacion`
- `actualizado_por`
- `fecha_actualizacion`

## 5. Apéndices

### 5.1 Matriz actor-permiso

| Función | Administrador | Encargado |
|---|:---:|:---:|
| Iniciar/cerrar sesión | X | X |
| Gestionar usuarios | X | - |
| Gestionar fincas | X | - |
| Gestionar asignación de encargado | X | - |
| Gestionar catálogo de razas | X | - |
| Registrar animal | X | X |
| Modificar animal | X | X |
| Eliminar animal | X* | - |
| Registrar/modificar pesajes | X | X |
| Eliminar pesajes | X | - |
| Registrar/modificar vacunaciones | X | X |
| Eliminar vacunaciones | X | - |
| Registrar/modificar monta reproductiva | X | X |
| Eliminar evento reproductivo | X | - |
| Modificar progenitores/genealogía | X | X |
| Registrar/modificar producción de leche | X | X |
| Eliminar producción de leche | X | - |
| Consultar todas las fincas | X | - |
| Consultar finca asignada | X | X |

\* Un animal solo puede eliminarse físicamente si no posee registros históricos relacionados. En caso contrario, debe conservarse y cambiar de estado.

### 5.2 Declaración de aceptación

La línea base oficial para BOVITRACK V1 son los requerimientos de usuario, funcionales, no funcionales y criterios de aceptación aquí descritos. Toda funcionalidad o modificación fuera del alcance debe evaluarse antes de incorporarse, considerando impacto en alcance, tiempo y viabilidad. La validación de V1 se realizará contra esta línea base.

**Responsable del desarrollo:** ____________________  
**Firma:** ____________________  
**Fecha:** ____________________

**Responsable de validación:** ____________________  
**Firma:** ____________________  
**Fecha:** ____________________

### Referencias base

- Kendall, K. E. y Kendall, J. E. (2011). *Systems analysis and design* (8.ª ed.). Pearson.
- Sommerville, I. (2011). *Ingeniería de software* (9.ª ed.). Pearson Educación.
