# BOVITRACK — Plan de trabajo por fases

## Propósito

Este documento sirve como contexto operativo para continuar el desarrollo de **BOVITRACK V1/MVP** con otra IA o agente de programación.

El proyecto dispone de aproximadamente **dos sprints de 15 días** para completar el desarrollo. Entre ambos puede existir una semana de parciales, por lo que el trabajo debe organizarse en fases pequeñas, evitando intentar construir todo el sistema al mismo tiempo.

La prioridad es completar el **MVP definido en el DERCAS**, sin ampliar el alcance.

---

# Estado actual

La fase documental inicial ya se encuentra prácticamente terminada.

Se dispone de:

- DERCAS V1/MVP.
- Requerimientos funcionales RF-01 a RF-10.
- Criterios de aceptación.
- Requerimientos no funcionales.
- Diagrama de contexto.
- Diagrama 0.
- Diagrama hijo 2.0.
- Documentación de arquitectura y diseño.
- Sistema de diseño basado en Microsoft Fluent 2.
- Paleta visual de BOVITRACK.
- Estructura documental bajo `docs/`.

Tecnologías previstas:

- PHP.
- Symfony.
- Doctrine ORM.
- MySQL 8.
- Twig.
- Symfony Forms.
- Tailwind CSS.
- Symfony UX Twig Components.
- Symfony UX Live Components.
- Symfony UX Turbo.
- Symfony UX Toolkit.
- Stimulus únicamente cuando sea necesario.

---


# Metodología de desarrollo

BOVITRACK se desarrollará mediante una **metodología incremental**.

Esto significa que el trabajo no debe organizarse como una secuencia de capas incompletas —por ejemplo, terminar primero toda la base de datos, después todos los controladores y al final toda la interfaz—. Cada iteración debe entregar una porción funcional del sistema que pueda ejecutarse, probarse y demostrarse.

## Principio de valor incremental

Cada incremento debe aportar **valor observable para el usuario o para la operación del sistema**.

Un incremento se considera válido cuando:

- parte de un requerimiento funcional o criterio de aceptación;
- atraviesa las capas necesarias para quedar utilizable;
- puede demostrarse de inicio a fin;
- conserva funcionando los incrementos anteriores;
- reduce riesgo para la siguiente iteración;
- no amplía el alcance del MVP.

La unidad de trabajo preferida será una **vertical funcional completa**:

```text
Requerimiento
      ↓
Modelo / persistencia necesaria
      ↓
Lógica y seguridad
      ↓
Formulario / interfaz
      ↓
Validaciones
      ↓
Prueba
      ↓
Valor demostrable
```

Por tanto, se debe evitar una estrategia como:

```text
Todas las entidades
      ↓
Todos los controladores
      ↓
Todos los formularios
      ↓
Todas las pruebas
```

porque durante demasiado tiempo no existiría una funcionalidad completa y verificable.

## Regla para cada iteración

Toda iteración debe responder:

1. ¿Qué usuario recibe valor?
2. ¿Qué puede hacer al finalizar que antes no podía hacer?
3. ¿Qué RF o criterio de aceptación queda cubierto?
4. ¿Cómo se demostrará?
5. ¿Qué prueba confirma que el incremento funciona?
6. ¿Qué funcionalidades anteriores deben seguir funcionando?

Las pruebas unitarias son obligatorias para toda lógica de dominio, validación o regla de negocio nueva. Un incremento no se considera terminado hasta que sus pruebas unitarias pasan y las pruebas existentes siguen pasando.

Si una tarea no produce valor demostrable por sí sola, debe:
- formar parte explícita de un incremento que sí lo haga, o
- considerarse una tarea técnica habilitadora con alcance corto y justificado.

## Incrementos previstos para la Fase 1

La Fase 1 debe ejecutarse como una sucesión de incrementos funcionales:

### Incremento 1 — Aplicación ejecutable
**Valor aportado:** existe una aplicación Symfony operativa, conectada a MySQL y preparada para evolucionar.

Incluye únicamente la infraestructura mínima indispensable:
- Symfony funcionando;
- configuración;
- MySQL;
- Doctrine;
- Twig;
- Tailwind;
- Symfony UX base.

Este es un incremento técnico habilitador y debe ser breve.

### Incremento 2 — Acceso seguro
**Valor aportado:** Administrador y Encargado pueden iniciar y cerrar sesión con permisos diferenciados.

Cubre principalmente:
- RF-01;
- Symfony Security;
- roles;
- hashing;
- restricciones iniciales.

### Incremento 3 — Organización, usuarios y fincas
**Valor aportado:** el Administrador puede crear la estructura operativa mínima de su organización y asignar un Encargado a una finca.

Cubre principalmente:
- RF-02;
- organización;
- usuarios;
- fincas;
- asignaciones;
- segregación de acceso.

### Incremento 4 — Catálogos utilizables
**Valor aportado:** el Administrador puede preparar las razas y vacunas que serán utilizadas posteriormente por el sistema.

Cubre:
- RF-03.

### Incremento 5 — Registro y consulta de animales
**Valor aportado:** un usuario autorizado puede registrar un animal y volver a consultarlo desde el sistema.

Cubre principalmente:
- RF-04;
- validación de arete;
- código interno;
- finca;
- raza;
- sexo;
- estado;
- permisos.

### Incremento 6 — Nacimientos y relaciones familiares
**Valor aportado:** el usuario puede registrar correctamente un nacimiento o un animal externo y almacenar sus relaciones de padre/madre.

Cubre:
- RF-05;
- origen;
- procedencia;
- progenitores.

### Incremento 7 — Ficha básica integrada
**Valor aportado:** el usuario puede abrir una ficha individual y visualizar de manera unificada la información básica ya registrada del animal.

Este incremento consolida el valor de RF-04 y RF-05 y deja preparada la ficha para recibir en la Fase 2:
- pesajes;
- vacunaciones;
- reproducción;
- leche;
- genealogía completa;
- historial integral.

## Criterio de cierre de un incremento

Un incremento no se considera terminado solo porque el código exista.

Debe cumplir:

- [ ] funciona desde la interfaz;
- [ ] persiste o consulta los datos correctos;
- [ ] respeta roles, organización y finca cuando aplique;
- [ ] valida entradas inválidas;
- [ ] cumple al menos un criterio de aceptación verificable;
- [ ] no rompe incrementos anteriores;
- [ ] puede demostrarse de principio a fin;
- [ ] el código queda integrado y versionado.


# Fase 0 — Fundamentos del proyecto

## Objetivo

Dejar definido el contexto técnico y funcional antes de programar.

## Estado

**Prácticamente completada.**

## Incluye

- contexto del proyecto;
- alcance V1/MVP;
- reglas de negocio;
- requerimientos;
- arquitectura;
- decisiones de frontend;
- sistema de diseño;
- componentes previstos;
- trazabilidad inicial.

Una vez verificada la documentación técnica restante, esta fase debe cerrarse y no convertirse en un bloqueo para comenzar a programar.

---

# Fase 1 — Núcleo funcional del sistema

## Objetivo

Convertir BOVITRACK de documentación a una aplicación Symfony funcional.

Al terminar esta fase debe existir una primera vertical completa del sistema:

> un usuario inicia sesión → accede según su rol → administra o consulta una finca → registra un animal → consulta su ficha.

Esta fase corresponde principalmente al **primer sprint de desarrollo**.

---

## 1. Inicializar el proyecto Symfony

Preparar el entorno base:

- crear o verificar el proyecto Symfony;
- configurar variables de entorno;
- configurar conexión con MySQL 8;
- instalar Doctrine ORM;
- comprobar migraciones;
- configurar Twig;
- configurar Symfony Forms;
- configurar Symfony Security;
- integrar Tailwind CSS;
- integrar Symfony UX requerido;
- comprobar que la aplicación inicia correctamente.

### Resultado esperado

El proyecto debe poder ejecutarse localmente sin errores y conectarse correctamente a la base de datos.

---

## 2. Definir e implementar el modelo de datos inicial

Crear las entidades principales necesarias para el MVP.

Entidades previstas:

- `OrganizacionGanadera`
- `Usuario`
- `Finca`
- `Raza`
- `Animal`
- `Vacuna`
- `Vacunacion`
- `Pesaje`
- `MontaReproductiva`
- `ProduccionLeche`

En esta fase deben implementarse primero las entidades necesarias para:

- autenticación;
- usuarios;
- fincas;
- catálogos;
- animales.

No crear tablas independientes para:

- genealogía;
- historial integral;
- total diario de leche.

Estos datos deben derivarse de otras relaciones.

---

## 3. Implementar RF-01 — Autenticación y control de acceso

Construir:

- inicio de sesión;
- cierre de sesión;
- hashing seguro de contraseñas;
- roles;
- autorización básica;
- segregación por organización;
- restricción del Encargado a su finca asignada.

### Resultado esperado

Debe existir al menos:

- una cuenta Administrador;
- una cuenta Encargado;
- acceso restringido correctamente según el rol.

---

## 4. Implementar RF-02 — Usuarios, fincas y asignaciones

Para Administrador:

- listar usuarios;
- crear usuarios;
- modificar usuarios;
- administrar fincas;
- crear fincas;
- modificar fincas;
- asignar un Encargado a una finca;
- cambiar su finca asignada.

Regla importante:

> un Encargado solo puede tener una finca activa asignada a la vez.

---

## 5. Implementar RF-03 — Catálogos básicos

Crear mantenimiento mínimo de:

- razas;
- vacunas.

El Administrador puede gestionar estos catálogos.

El Encargado únicamente podrá consultar o seleccionar elementos disponibles donde corresponda.

---

## 6. Implementar RF-04 — Gestión de animales

Este es uno de los módulos centrales de BOVITRACK.

Debe permitir:

- registrar animal;
- consultar animales;
- modificar animal;
- generar código interno global único;
- validar arete único dentro de la organización;
- asociar finca;
- asociar raza;
- registrar sexo;
- registrar fecha de nacimiento;
- registrar origen;
- registrar procedencia;
- registrar padre y madre opcionales;
- indicar si es productora de leche;
- manejar estado del animal.

Debe respetar permisos de Administrador y Encargado.

---

## 7. Implementar RF-05 — Nacimientos y genealogía base

Debe distinguir:

### Animal nacido en finca

- asignar automáticamente la finca de nacimiento;
- establecer origen `NACIDO_EN_FINCA`.

### Animal externo

- utilizar el registro general de animales;
- establecer origen `EXTERNO`;
- permitir procedencia descriptiva.

También deben quedar operativas las relaciones:

- padre;
- madre.

La genealogía completa se desarrollará posteriormente utilizando estas relaciones.

---

## 8. Crear la primera ficha del animal

Al finalizar la Fase 1 debe existir `/animals/{id}` o equivalente.

La ficha debe mostrar al menos:

- código interno;
- arete;
- nombre;
- sexo;
- raza;
- finca;
- fecha de nacimiento;
- origen;
- procedencia;
- estado;
- padre;
- madre.

Todavía no es necesario que todos los apartados históricos estén completos.

---

# Frontend durante la Fase 1

La interfaz debe construirse desde el principio usando las decisiones ya documentadas.

## Regla tecnológica

1. Twig para contenido renderizado por servidor.
2. Twig Components para UI reutilizable.
3. Live Components para interacción con PHP/BD sin recarga completa.
4. Turbo para navegación o actualización parcial.
5. Stimulus solo para comportamiento exclusivamente del navegador.

## Diseño

Utilizar:

- Microsoft Fluent 2 como sistema de diseño base;
- Tailwind CSS para implementar estilos;
- Fluent System Icons;
- componentes propios de BOVITRACK.

No introducir:

- Bootstrap;
- React;
- Vue;
- frontend SPA independiente.

---

# Definition of Done de la Fase 1

La Fase 1 solo puede considerarse terminada cuando:

- [ ] Symfony inicia correctamente.
- [ ] MySQL está conectado.
- [ ] Doctrine y migraciones funcionan.
- [ ] Existe autenticación.
- [ ] Administrador y Encargado poseen permisos diferentes.
- [ ] Existe segregación por organización.
- [ ] El Encargado está restringido a su finca.
- [ ] Usuarios y fincas pueden administrarse.
- [ ] Razas y vacunas poseen catálogo básico.
- [ ] Los animales pueden registrarse y modificarse.
- [ ] El código interno del animal es globalmente único.
- [ ] El arete se valida dentro de la organización.
- [ ] Se distingue entre animal nacido en finca y animal externo.
- [ ] Padre y madre pueden almacenarse.
- [ ] Existe una ficha básica del animal.
- [ ] La interfaz utiliza la arquitectura frontend definida.
- [ ] No se ha agregado funcionalidad fuera del MVP.

---

# Fase 2 — Operaciones e integración

La segunda fase completa el ciclo operativo del animal.

Debe incluir:

- RF-06 — Pesajes.
- RF-07 — Vacunaciones.
- RF-08 — Monta reproductiva.
- RF-09 — Producción de leche.
- RF-10 — Consulta integral del animal.
- genealogía hasta tres generaciones;
- historial integral;
- validaciones finales;
- responsive;
- pruebas;
- corrección de errores;
- preparación de la demostración.

---

# Semana de parciales

Si existe una semana de parciales entre ambas fases, no debe utilizarse para desarrollar módulos grandes.

Solo realizar:

- corrección de bugs pequeños;
- actualización de documentación;
- revisión de backlog;
- preparación de la siguiente fase;
- tareas de mantenimiento de bajo riesgo.

---

# Regla principal para cualquier IA o agente

No ampliar el alcance.

Antes de implementar una funcionalidad:

1. identificar el RF correspondiente;
2. leer sus criterios de aceptación;
3. identificar entidades afectadas;
4. verificar permisos;
5. verificar restricciones por organización y finca;
6. implementar el cambio más pequeño posible;
7. probarlo;
8. comprobar que no rompe funcionalidades anteriores.

La prioridad es **terminar BOVITRACK V1/MVP**, no agregar funcionalidades nuevas.

Además, el agente debe trabajar siempre de forma incremental: **cada iteración debe terminar con una funcionalidad utilizable, comprobable y demostrable**, salvo tareas técnicas habilitadoras breves y explícitamente justificadas.
